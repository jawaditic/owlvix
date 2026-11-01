<?php

namespace App\Http\Controllers;

use App\Models\BuilderAccount;
use App\Models\BuilderProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Projects saved to a builder account by the page builders and the email builder (public/js/landing-builder.js,
 * public/js/email-builder.js): "My sites" lists them, Save stores the whole editable project, Open brings it back.
 */
class BuilderProjectController extends Controller
{
    /** GET /builder/projects?kind=website — this account's projects of one kind, newest first. */
    public function index(Request $request): JsonResponse
    {
        $kind = $request->query('kind');
        if (! in_array($kind, BuilderProject::KINDS, true)) {
            return $this->fail('Unknown builder.', 422);
        }

        return response()->json(['ok' => true, 'projects' => $this->account($request)->projects()->where('kind', $kind)
            ->latest('updated_at')->get(['id', 'kind', 'name', 'updated_at'])->map->toListItem()->values()]);
    }

    /** GET /builder/projects/{id} — one project, to open in the builder. */
    public function show(Request $request, string $id): JsonResponse
    {
        $p = $this->account($request)->projects()->find($id);
        if (! $p) {
            return $this->fail('This project was not found. It may have been deleted.', 404);
        }

        return response()->json(['ok' => true, 'project' => $p->toListItem() + ['data' => json_decode($p->data, true)]]);
    }

    /** POST /builder/projects — save a new project, or update one (with its id). */
    public function save(Request $request): JsonResponse
    {
        $v = $request->validate([
            'id' => ['nullable', 'string', 'regex:/^[a-z0-9]{16}$/'],
            'kind' => ['required', 'string', 'in:'.implode(',', BuilderProject::KINDS)],
            'name' => ['nullable', 'string', 'max:200'],
            'data' => ['required', 'array'],
        ]);
        $json = json_encode($v['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > BuilderProject::MAX_BYTES) {
            return $this->fail('This project is too big to save (most often: very large pictures). Use smaller pictures and try again.', 413);
        }
        $account = $this->account($request);
        $name = Str::limit(trim(strip_tags($v['name'] ?? '')) ?: 'Untitled', 80, '');

        if (! empty($v['id'])) {
            $p = $account->projects()->find($v['id']);
            if (! $p) {
                return $this->fail('This project was not found. It may have been deleted.', 404);
            }
            if ($p->kind !== $v['kind']) {
                return $this->fail('This project belongs to another builder.', 422);
            }
        } else {
            if ($account->projects()->where('kind', $v['kind'])->count() >= BuilderProject::MAX_PER_KIND) {
                return $this->fail('You can save up to '.BuilderProject::MAX_PER_KIND.' projects here. Delete one you no longer need first.', 422);
            }
            $p = new BuilderProject(['kind' => $v['kind']]);
            $p->id = $this->newId();
            $p->builder_account_id = $account->id;
        }
        $p->name = $name;
        $p->data = $json;
        $p->save();

        return response()->json(['ok' => true, 'project' => $p->toListItem()]);
    }

    /** POST /builder/projects/{id}/delete */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $p = $this->account($request)->projects()->find($id);
        if (! $p) {
            return $this->fail('This project was not found.', 404);
        }
        $p->delete();

        return response()->json(['ok' => true]);
    }

    private function account(Request $request): BuilderAccount
    {
        return $request->attributes->get('builder_account');
    }

    private function newId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (BuilderProject::whereKey($id)->exists());

        return $id;
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $message], $status);
    }
}
