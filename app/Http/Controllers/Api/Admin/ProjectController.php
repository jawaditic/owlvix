<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Reorder;
use Illuminate\Http\Request;

/** Portfolio in the mobile app. Updates use POST because phones send images as multipart forms. */
class ProjectController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Project::ordered()->get()->map->toApi(),
            'categories' => Project::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['sort_order'] = 0; // new projects go to the top
        Project::query()->increment('sort_order');

        return response()->json(Project::create($data)->toApi(), 201);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, false);
        if (isset($data['image'])) {
            $project->deleteUploadedImage();
        }
        $project->update($data);

        return response()->json($project->toApi());
    }

    public function destroy(Project $project)
    {
        $project->deleteUploadedImage();
        $project->delete();

        return response()->json(['ok' => true]);
    }

    public function move(Project $project, string $direction)
    {
        Reorder::move(Project::ordered()->get(), $project, $direction);

        return $this->index();
    }

    public function toggle(Project $project)
    {
        $project->forceFill(['is_published' => ! $project->is_published])->save();

        return response()->json($project->toApi());
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate(Project::rules($creating), Project::RULE_MESSAGES);
        $data['is_published'] = $request->boolean('is_published');
        if ($request->hasFile('image')) {
            $data['image'] = Project::storeImage($request->file('image'), $data['title']);
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
