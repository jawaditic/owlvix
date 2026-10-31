<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuilderAccount;
use App\Models\BuilderApp;
use App\Models\BuilderForm;
use App\Models\BuilderSite;

/** Admin → Page builder: sites published with the free builders, their forms, and apps made with the mobile app builder. */
class BuilderController extends Controller
{
    public function index()
    {
        return view('admin.builder', [
            'sites' => BuilderSite::with('account')->orderByRaw('reported_at is null')->orderByDesc('reported_at')->orderByDesc('updated_at')->get(),
            'apps' => BuilderApp::with('account')->withCount(['builds' => fn ($q) => $q->where('status', 'done')])->orderByRaw('reported_at is null')->orderByDesc('reported_at')->orderByDesc('updated_at')->get(),
            'forms' => BuilderForm::with('account')->withCount('leads')->orderByDesc('last_submission_at')->orderByDesc('created_at')->get(),
            'stats' => [
                'accounts' => BuilderAccount::count(),
                'live' => BuilderSite::where('status', 'live')->count(),
                'reported' => BuilderSite::where('status', 'live')->whereNotNull('reported_at')->count(),
                'messages' => (int) BuilderForm::where('kind', 'contact')->sum('submissions'),
                'leads' => \App\Models\BuilderLead::count(),
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

    /** Takes an app offline: phones show "no longer available", its pictures and files go private, the owner can't change or build it. */
    public function takedownApp(BuilderApp $app)
    {
        $app->update(['status' => 'removed']);
        \App\Support\AppContent::setLive($app, false);

        return back()->with('status', 'The app '.$app->name.' was taken down.');
    }

    public function restoreApp(BuilderApp $app)
    {
        $app->forceFill(['status' => 'live', 'reported_at' => null])->save();
        \App\Support\AppContent::setLive($app, true);

        return back()->with('status', 'The app '.$app->name.' is live again.');
    }

    public function dismissApp(BuilderApp $app)
    {
        $app->forceFill(['reported_at' => null])->save();

        return back()->with('status', 'Report on the app '.$app->name.' dismissed.');
    }

    public function toggleForm(BuilderForm $form)
    {
        $form->forceFill(['disabled_at' => $form->disabled_at ? null : now()])->save();

        return back()->with('status', 'Form for '.$form->site_name.($form->disabled_at ? ' disabled.' : ' enabled.'));
    }
}
