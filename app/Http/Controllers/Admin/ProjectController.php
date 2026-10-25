<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Reorder;
use Illuminate\Http\Request;

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
        Reorder::move(Project::ordered()->get(), $project, $direction);

        return back();
    }

    public function toggle(Project $project)
    {
        $project->forceFill(['is_published' => ! $project->is_published])->save();

        return back();
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
