{{-- The page builder editor shell (public/js/landing-builder.js fills it in). Used by the landing page builder and the
     extra builders; pass $mode (website, portfolio, links, soon) to start the editor in a kit. --}}
<section class="lpb-app" data-lpb @if (! empty($mode)) data-mode="{{ $mode }}" @endif data-runtime="{{ \App\Support\BuilderHtml::runtimeUrl() }}" data-site-base="{{ \App\Models\BuilderSite::base() }}">
    <div class="lpb-top">
        <div class="lpb-top__group">
            <label class="sr-only" for="lpb-pages">Page</label>
            <select id="lpb-pages" data-pages class="lpb-select"></select>
            <button type="button" class="lpb-tb" data-act="add-page" title="Add a page">＋ Page</button>
        </div>
        <div class="lpb-top__group lpb-devices" role="group" aria-label="Preview size">
            <button type="button" data-device="desktop" aria-pressed="true" title="Desktop">🖥</button>
            <button type="button" data-device="tablet" aria-pressed="false" title="Tablet">▭</button>
            <button type="button" data-device="mobile" aria-pressed="false" title="Mobile">📱</button>
        </div>
        <div class="lpb-top__group">
            <button type="button" class="lpb-tb" data-act="undo" title="Undo (Ctrl/Cmd+Z)">↩ Undo</button>
            <button type="button" class="lpb-tb" data-act="redo" title="Redo (Ctrl/Cmd+Shift+Z)">↪ Redo</button>
            <button type="button" class="lpb-tb" data-act="preview" title="Open a preview in a new tab">👁 Preview</button>
            <button type="button" class="lpb-tb" data-act="open" title="Open a saved project">📂 Open</button>
            <button type="button" class="lpb-tb" data-act="save" title="Download a project file to keep editing later">💾 Save project</button>
        </div>
        <div class="lpb-top__group">
            <button type="button" class="btn btn--sm btn--ghost" data-act="download">Download ↓</button>
            <button type="button" class="btn btn--sm btn--glow" data-act="publish">Publish 🚀</button>
            <button type="button" class="lpb-tb lpb-full-btn" data-act="fullscreen" title="Full screen (Esc to exit)" aria-pressed="false">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="i-enter"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                <svg viewBox="0 0 24 24" aria-hidden="true" class="i-exit"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>
                <span>Full screen</span>
            </button>
        </div>
    </div>

    <div class="lpb-body">
        <aside class="lpb-side">
            <div class="lpb-tabs" role="tablist">
                <button type="button" role="tab" data-tab="add" aria-selected="true">＋ Add</button>
                <button type="button" role="tab" data-tab="edit" aria-selected="false">Edit</button>
                <button type="button" role="tab" data-tab="page" aria-selected="false">Page</button>
                <button type="button" role="tab" data-tab="site" aria-selected="false">Site</button>
            </div>
            <div class="lpb-panel is-active" data-tab-panel="add">
                <input type="search" class="lpb-search" data-add-search placeholder="Search widgets &amp; templates…" aria-label="Search widgets and templates">
                <p class="lpb-hint">Drag onto the page — or click to add below the selected item.</p>
                <div data-add-list></div>
            </div>
            <div class="lpb-panel" data-tab-panel="edit">
                <div class="lpb-etabs" role="tablist">
                    <button type="button" role="tab" data-etab="content" aria-selected="true">Content</button>
                    <button type="button" role="tab" data-etab="style" aria-selected="false">Style</button>
                    <button type="button" role="tab" data-etab="advanced" aria-selected="false">Advanced</button>
                </div>
                <div data-edit-panel></div>
            </div>
            <div class="lpb-panel" data-tab-panel="page"><div data-page-panel></div></div>
            <div class="lpb-panel" data-tab-panel="site"><div data-site-panel></div></div>
            <p class="lpb-autosave" data-autosave>Saved on this device</p>
        </aside>
        <div class="lpb-canvas">
            <div class="lpb-screen" data-device="desktop">
                <iframe data-lpb-frame title="Your page — click text or images to edit"></iframe>
            </div>
        </div>
    </div>

    <input type="file" accept="image/*" data-pick-image hidden>
    <input type="file" accept=".json,application/json" data-open-file hidden>
    <div class="lpb-toast" data-toast role="status" hidden></div>

    <dialog class="lpb-dialog" data-builder-dialog aria-label="Builder account">
        <button type="button" class="lpb-dialog__x" data-dialog-close aria-label="Close">×</button>
        <div data-dialog-body></div>
    </dialog>
</section>

