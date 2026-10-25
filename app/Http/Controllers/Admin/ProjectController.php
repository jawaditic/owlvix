<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin → Portfolio: projects shown in "Our work" on the homepage. */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::ordered()
            ->when(isset(Project::CATEGORIES[$request->input('category')]), fn ($q) => $q->where('category', $request->input('category')))
            ->get();

        return view('admin.projects.index', ['projects' => $projects, 'counts' => Project::selectRaw('category, count(*) n')->groupBy('category')->pluck('n', 'category')]);
    }

    public function create()
    {
        return view('admin.projects.form', ['project' => new Project(['category' => 'website', 'is_published' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['sort_order'] = 0; // new projects go to the top
        Project::query()->increment('sort_order');
        Project::create($data);

        return redirect()->route('admin.projects.index')->with('status', 'Project added.');
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, false);
        if (isset($data['image'])) {
            $project->deleteUploadedImage();
        }
        $project->update($data);

        return redirect()->route('admin.projects.index')->with('status', 'Project saved.');
    }

    public function destroy(Project $project)
    {
        $project->deleteUploadedImage();
        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', 'Project deleted.');
    }

    public function move(Project $project, string $direction)
    {
        $all = Project::ordered()->get()->values();
        $i = $all->search(fn ($p) => $p->is($project));
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $all->count()) {
            [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
            $all->each(fn ($p, $n) => $p->forceFill(['sort_order' => $n + 1])->save());
        }

        return back();
    }

    public function toggle(Project $project)
    {
        $project->forceFill(['is_published' => ! $project->is_published])->save();

        return back();
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Project::CATEGORIES))],
            'summary' => ['nullable', 'string', 'max:300'],
            'url' => ['nullable', 'url', 'max:255'],
            'ios_url' => ['nullable', 'url', 'max:255'],
            'android_url' => ['nullable', 'url', 'max:255'],
            'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.required' => 'Please add a screenshot or mockup of the project.',
            'image.max' => 'The image must be 5 MB or smaller.',
        ]);
        $data['is_published'] = $request->boolean('is_published');

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dir = public_path('uploads/portfolio');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = Str::slug($data['title']).'-'.Str::lower(Str::random(6)).'.'.$file->extension();
            $file->move($dir, $name);
            $data['image'] = 'uploads/portfolio/'.$name;
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
