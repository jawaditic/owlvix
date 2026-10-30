{{-- Free image compressor — runs fully in the browser (canvas), images are never uploaded. --}}
@extends('layouts.page')

@php
    $faq = [
        ['Are my images uploaded anywhere?', 'No. Everything happens inside your browser on your own device. Your images never leave your computer or phone.'],
        ['Why WebP?', 'WebP images are usually 25–35% smaller than JPEG at the same quality and are supported by every modern browser. Smaller images make your pages load faster, which helps visitors and Google rankings.'],
        ['What quality should I choose?', '80% is a good default: the difference is very hard to see, and files are much smaller. Use 85–90% for photos where detail really matters, and 70% for thumbnails.'],
        ['What size should website images be?', 'Rarely wider than 1920 pixels for full-width banners, around 1200 pixels for content images, and 800 pixels or less for cards and thumbnails.'],
    ];
@endphp

@section('title', 'Image Compressor — Convert to WebP Free, No Upload')
@section('description', 'Compress and resize images and convert JPG or PNG to WebP — right in your browser, nothing is uploaded. Make your website faster. Free, no sign-up.')
@section('canonical', route('image-compressor'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Image Compressor', 'url' => route('image-compressor'), 'category' => 'MultimediaApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Image compressor</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Nothing is uploaded</span>
        <h1>Make your images <span class="accent">smaller &amp; faster</span></h1>
        <p class="lead">Compress, resize and convert JPG and PNG to WebP — right in your browser. Your images never leave your device.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="imgc card">
            <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</span>
            <label class="imgc-drop" data-drop>
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp" multiple data-files>
                <span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('rocket') !!}</span>
                <strong>Drop images here or click to choose</strong>
                <small>JPG, PNG, WebP or GIF · up to 30 images · processed on your device</small>
            </label>
            <div class="imgc-opts">
                <label>Format
                    <select data-o="format"><option value="image/webp" selected>WebP (smallest)</option><option value="image/jpeg">JPEG</option><option value="keep">Keep original format</option></select>
                </label>
                <label>Quality <b data-o="q-out">80%</b>
                    <input type="range" min="40" max="95" step="5" value="80" data-o="quality">
                </label>
                <label>Max width
                    <select data-o="width"><option value="0">Keep size</option><option value="2560">2560 px</option><option value="1920" selected>1920 px (banners)</option><option value="1200">1200 px (content)</option><option value="800">800 px (cards)</option><option value="400">400 px (thumbnails)</option></select>
                </label>
            </div>
        </div>
        <div class="imgc-results" data-results hidden>
            <div class="imgc-total card">
                <div><small>Total saved</small><b data-total>–</b></div>
                <button type="button" class="btn btn--glow" data-all>Download all</button>
            </div>
            <div class="imgc-list" data-list></div>
        </div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Want your whole site this fast?</h2><p>We optimise every image, set up lazy loading and caching, and fix whatever else slows your pages — then prove it with a speed test.</p></div>
            <a href="{{ route('speed-test') }}" class="btn btn--glow">Test my speed <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Image optimisation, explained'])

<script>
(() => {
    const $ = s => document.querySelector(s), O = n => $(`[data-o="${n}"]`);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const kb = b => b >= 1048576 ? (b / 1048576).toFixed(2) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    const ext = { 'image/webp': 'webp', 'image/jpeg': 'jpg', 'image/png': 'png' };
    let files = [], results = [];

    const compress = async file => {
        const bmp = await createImageBitmap(file).catch(() => null);
        if (!bmp) return { file, error: 'Could not read this image' };
        const max = +O('width').value, scale = max && bmp.width > max ? max / bmp.width : 1;
        const w = Math.round(bmp.width * scale), h = Math.round(bmp.height * scale);
        const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
        const ctx = canvas.getContext('2d');
        let type = O('format').value === 'keep' ? (ext[file.type] ? file.type : 'image/png') : O('format').value;
        if (type === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h); } // JPEG has no transparency
        ctx.drawImage(bmp, 0, 0, w, h);
        let blob = await new Promise(r => canvas.toBlob(r, type, O('quality').value / 100));
        if (!blob || (blob.type !== type)) { type = 'image/jpeg'; blob = await new Promise(r => canvas.toBlob(r, type, O('quality').value / 100)); } // browser can't make WebP
        const kept = blob.size >= file.size && scale === 1; // never make a file bigger
        return { file, blob: kept ? file : blob, type: kept ? file.type : type, w, h, kept, name: file.name.replace(/\.[^.]+$/, '') + (kept ? file.name.match(/\.[^.]+$/)?.[0] || '' : '.' + ext[type]) };
    };

    const render = () => {
        results.forEach(r => r.url && URL.revokeObjectURL(r.url));
        results.forEach(r => { if (r.blob) r.url = URL.createObjectURL(r.blob); });
        const before = results.reduce((s, r) => s + r.file.size, 0), after = results.reduce((s, r) => s + (r.blob?.size ?? r.file.size), 0);
        $('[data-total]').textContent = `${kb(before - after)} (${before ? Math.round((1 - after / before) * 100) : 0}%) · ${kb(before)} → ${kb(after)}`;
        $('[data-list]').innerHTML = results.map((r, i) => r.error ? `<div class="imgc-item card"><div><b>${esc(r.file.name)}</b><small>${esc(r.error)}</small></div></div>` : `
            <div class="imgc-item card">
                <img src="${r.url}" alt="" width="64" height="64">
                <div><b>${esc(r.name)}</b><small>${r.w} × ${r.h}px · ${kb(r.file.size)} → <strong>${kb(r.blob.size)}</strong></small></div>
                <span class="imgc-save" data-kept="${r.kept ? 1 : 0}">${r.kept ? 'Already small' : '−' + Math.round((1 - r.blob.size / r.file.size) * 100) + '%'}</span>
                <a class="btn btn--sm" href="${r.url}" download="${esc(r.name)}" data-i="${i}">Download</a>
            </div>`).join('');
        $('[data-results]').hidden = !results.length;
    };

    const run = async () => {
        if (!files.length) return;
        $('[data-list]').innerHTML = '<p class="imgc-busy">Compressing…</p>'; $('[data-results]').hidden = false;
        results = [];
        for (const f of files) results.push(await compress(f));
        render();
        window.track?.('image_compress', { count: files.length });
    };

    const take = list => { files = [...list].filter(f => f.type.startsWith('image/')).slice(0, 30); run(); };
    $('[data-files]').addEventListener('change', e => take(e.target.files));
    const drop = $('[data-drop]');
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', e => take(e.dataTransfer.files));
    O('quality').addEventListener('input', () => O('q-out').textContent = O('quality').value + '%');
    ['format', 'width'].forEach(n => O(n).addEventListener('change', run));
    O('quality').addEventListener('change', run);
    $('[data-all]').addEventListener('click', () => document.querySelectorAll('[data-list] a[download]').forEach((a, i) => setTimeout(() => a.click(), i * 250)));
})();
</script>
@endsection
