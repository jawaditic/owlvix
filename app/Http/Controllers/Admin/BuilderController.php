<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuilderAccount;
use App\Models\BuilderForm;
use App\Models\BuilderSite;

/** Admin → Page builder: sites published with the free landing page builder, and their contact forms. */
class BuilderController extends Controller
{
    public function index()
    {
        return view('admin.builder', [
            'sites' => BuilderSite::with('account')->orderByRaw('reported_at is null')->orderByDesc('reported_at')->orderByDesc('updated_at')->get(),
            'forms' => BuilderForm::with('account')->orderByDesc('last_submission_at')->orderByDesc('created_at')->get(),
            'stats' => [
                'accounts' => BuilderAccount::count(),
                'live' => BuilderSite::where('status', 'live')->count(),
                'reported' => BuilderSite::where('status', 'live')->whereNotNull('reported_at')->count(),
                'messages' => (int) BuilderForm::sum('submissions'),
            ],
        ]);
    }

    public function takedown(BuilderSite $site)
    {
        $site->update(['status' => 'removed', 'removed_by' => 'admin']);
        $site->syncPublicFiles(); // takes the static copy offline; the master copy stays for Restore

        return back()->with('status', $site->displayUrl().' was taken down.');
    }

    public function restore(BuilderSite $site)
    {
        abort_unless($site->removed_by === 'admin', 422, 'The owner removed this site; only they can publish it again.');
        $site->forceFill(['status' => 'live', 'removed_by' => null, 'reported_at' => null])->save();
        $site->syncPublicFiles();

        return back()->with('status', $site->displayUrl().' is live again.');
    }

    public function dismiss(BuilderSite $site)
    {
        $site->forceFill(['reported_at' => null])->save();

        return back()->with('status', 'Report on '.$site->displayUrl().' dismissed.');
    }

    public function toggleForm(BuilderForm $form)
    {
        $form->forceFill(['disabled_at' => $form->disabled_at ? null : now()])->save();

        return back()->with('status', 'Form for '.$form->site_name.($form->disabled_at ? ' disabled.' : ' enabled.'));
    }
}
