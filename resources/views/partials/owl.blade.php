{{-- Vector owl. Every moving part is its own group; owl.js animates them (see createRig).
     $u keeps gradient/clip ids unique when two owls are on one page.
     $variant: 'teacher' (default: cap + pointer stick) or 'dev' (headset + laptop with code). --}}
@php($u = 'owl'.\Illuminate\Support\Str::random(6))
@php($dev = ($variant ?? 'teacher') === 'dev')
<svg class="owl-svg{{ $dev ? ' owl-svg--dev' : '' }}" viewBox="0 0 400 420" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $alt ?? 'Owlvix owl' }}">
  <defs>
    <linearGradient id="{{ $u }}-fur" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#9a8a80"/><stop offset="1" stop-color="#6c5c54"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-furSide" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#8d7c72"/><stop offset="1" stop-color="#5d4f48"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-belly" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fbf1de"/><stop offset="1" stop-color="#ead7b5"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-cap" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#3f7be0"/><stop offset="1" stop-color="#1f4f9e"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-beak" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffc04d"/><stop offset="1" stop-color="#ee8f1c"/>
    </linearGradient>
    <radialGradient id="{{ $u }}-iris" cx=".4" cy=".35" r=".7">
      <stop offset="0" stop-color="#6aa8ff"/><stop offset="1" stop-color="#1f5fc4"/>
    </radialGradient>
    <clipPath id="{{ $u }}-eyeL"><circle cx="160" cy="150" r="38"/></clipPath>
    <clipPath id="{{ $u }}-eyeR"><circle cx="250" cy="150" r="38"/></clipPath>
  </defs>

  <!-- Feet stay planted; the body leans over them -->
  <g class="o-foot o-foot-l">
    <path d="M134 414c-7-13 4-24 15-17 4-11 20-11 23 0 11-7 22 4 15 17z" fill="#f2a12e" stroke="#3a2a22" stroke-width="3" stroke-linejoin="round"/>
  </g>
  <g class="o-foot o-foot-r">
    <path d="M226 414c-7-13 4-24 15-17 4-11 20-11 23 0 11-7 22 4 15 17z" fill="#f2a12e" stroke="#3a2a22" stroke-width="3" stroke-linejoin="round"/>
  </g>

  <g class="o-body">
    <!-- body + belly -->
    <path d="M205 214c70 0 100 60 100 114 0 48-38 72-100 72s-100-24-100-72c0-54 30-114 100-114z" fill="url(#{{ $u }}-fur)" stroke="#3a2a22" stroke-width="4"/>
    <path d="M205 262c40 0 62 40 62 76 0 32-24 50-62 50s-62-18-62-50c0-36 22-76 62-76z" fill="url(#{{ $u }}-belly)"/>
    <g fill="none" stroke="#b49a7a" stroke-width="3" stroke-linecap="round">
      <path d="M170 300q8 9 17 0q9 9 18 0q9 9 18 0q9 9 17 0"/>
      <path d="M162 330q9 9 18 0q9 9 18 0q9 9 18 0q9 9 18 0q9 9 18 0" transform="translate(-4 0)"/>
      <path d="M170 358q9 9 18 0q9 9 18 0q9 9 18 0q8 8 16 0"/>
    </g>

    @if ($dev)
    <!-- laptop held in front of the body, screen facing out -->
    <g class="o-laptop">
      <rect x="118" y="252" width="174" height="112" rx="10" fill="#cbd5e1" stroke="#3a2a22" stroke-width="3.5"/>
      <rect x="128" y="262" width="154" height="92" rx="5" fill="#0f172a"/>
      <circle cx="205" cy="257" r="2" fill="#64748b"/>
      <text x="205" y="306" text-anchor="middle" font-family="ui-monospace, Menlo, monospace" font-size="34" font-weight="700" fill="#34e08f">&lt;/&gt;</text>
      <g stroke-linecap="round" stroke-width="4">
        <path d="M142 326h40" stroke="#4f8cff"/><path d="M188 326h22" stroke="#a78bfa"/>
        <path d="M150 340h28" stroke="#34e08f"/><path d="M184 340h46" stroke="#64748b"/>
        <path d="M236 326h30" stroke="#f4c430"/>
      </g>
      <path d="M104 364h202l-12 16H116z" fill="#94a3b8" stroke="#3a2a22" stroke-width="3.5" stroke-linejoin="round"/>
    </g>
    @endif

    <!-- free wing, in front of the body's side -->
    <g class="o-wing">
      <path d="M262 252c42 4 72 40 70 84-1 22-12 36-26 30-6 8-18 8-24-2-10 4-20-2-20-12-14-8-18-24-14-40 4-20 8-40 14-60z" fill="url(#{{ $u }}-furSide)" stroke="#3a2a22" stroke-width="3.5" stroke-linejoin="round"/>
      <path d="M306 346c-2-14-6-26-14-36M284 352c0-12-2-22-8-32" fill="none" stroke="#3a2a22" stroke-width="2.5" stroke-linecap="round" opacity=".55"/>
    </g>

    @if ($dev)
    <!-- left wing resting on the laptop (named o-arm so the rig moves it) -->
    <g class="o-arm">
      <g transform="translate(410 0) scale(-1 1)">
        <path d="M262 252c42 4 72 40 70 84-1 22-12 36-26 30-6 8-18 8-24-2-10 4-20-2-20-12-14-8-18-24-14-40 4-20 8-40 14-60z" fill="url(#{{ $u }}-furSide)" stroke="#3a2a22" stroke-width="3.5" stroke-linejoin="round"/>
        <path d="M306 346c-2-14-6-26-14-36M284 352c0-12-2-22-8-32" fill="none" stroke="#3a2a22" stroke-width="2.5" stroke-linecap="round" opacity=".55"/>
      </g>
    </g>
    @else
    <!-- stick arm (wing holding the pointer) -->
    <g class="o-arm">
      <line x1="28" y1="66" x2="98" y2="306" stroke="#3a2a22" stroke-width="10" stroke-linecap="round"/>
      <line x1="28" y1="66" x2="98" y2="306" stroke="#d9a066" stroke-width="5.5" stroke-linecap="round"/>
      <circle cx="29" cy="69" r="4.5" fill="#3a2a22"/>
      <path d="M156 256c-22-6-44-8-62-2-8 2-10 10-6 16-6 8-2 18 8 20 4 8 14 10 22 6 14 8 28 12 42 14z" fill="url(#{{ $u }}-furSide)" stroke="#3a2a22" stroke-width="3.5" stroke-linejoin="round"/>
      <path d="M90 270c6 2 14 2 22-2M96 288c6 1 12 0 18-4" fill="none" stroke="#3a2a22" stroke-width="2.5" stroke-linecap="round" opacity=".6"/>
    </g>
    @endif

    <!-- head -->
    <g class="o-head">
      <!-- ear tufts -->
      <path d="M104 98l-6-46 40 26z" fill="#6c5c54" stroke="#3a2a22" stroke-width="3.5" stroke-linejoin="round"/>
      <path d="M306 98l6-46-40 26z" fill="#6c5c54" stroke="#3a2a22" stroke-width="3.5" stroke-linejoin="round"/>
      <circle cx="205" cy="150" r="108" fill="url(#{{ $u }}-fur)" stroke="#3a2a22" stroke-width="4"/>
      <!-- face discs -->
      <ellipse cx="160" cy="156" rx="56" ry="60" fill="#efe1c8"/>
      <ellipse cx="250" cy="156" rx="56" ry="60" fill="#efe1c8"/>

      <!-- eyes -->
      <g class="o-eye">
        <circle cx="160" cy="150" r="38" fill="#fff"/>
        <circle cx="250" cy="150" r="38" fill="#fff"/>
        <g class="o-pupils">
          <circle cx="166" cy="152" r="16" fill="url(#{{ $u }}-iris)"/>
          <circle cx="256" cy="152" r="16" fill="url(#{{ $u }}-iris)"/>
          <circle cx="166" cy="152" r="7.5" fill="#0e1b33"/>
          <circle cx="256" cy="152" r="7.5" fill="#0e1b33"/>
          <circle cx="161" cy="146" r="4.5" fill="#fff"/>
          <circle cx="251" cy="146" r="4.5" fill="#fff"/>
        </g>
        <!-- eyelids: scale down from the top to blink -->
        <g clip-path="url(#{{ $u }}-eyeL)"><rect class="o-lid" x="118" y="110" width="84" height="82" fill="#8a7a70"/></g>
        <g clip-path="url(#{{ $u }}-eyeR)"><rect class="o-lid" x="208" y="110" width="84" height="82" fill="#8a7a70"/></g>
      </g>

      <!-- glasses -->
      <g fill="none" stroke="#141414" stroke-width="9">
        <circle cx="160" cy="150" r="43"/>
        <circle cx="250" cy="150" r="43"/>
        <path d="M200 140q5-6 10 0" stroke-width="7"/>
        <path d="M292 140l26-6" stroke-width="7" stroke-linecap="round"/>
      </g>
      <g fill="#fff" opacity=".55"><path d="M134 128a32 32 0 0 1 22-14l2 6a26 26 0 0 0-18 12z"/><path d="M224 128a32 32 0 0 1 22-14l2 6a26 26 0 0 0-18 12z"/></g>

      <!-- brows -->
      <g class="o-brows" fill="none" stroke="#4a3328" stroke-width="7" stroke-linecap="round">
        <path d="M136 100q22-12 44-2"/>
        <path d="M230 98q22-10 44 2"/>
      </g>

      <!-- beak: mouth opening, lower beak (jaw) and upper beak -->
      <ellipse class="o-mouth" cx="205" cy="206" rx="13" ry="10" fill="#6b1d1d"/>
      <path class="o-jaw" d="M193 200q12 26 24 0z" fill="#e8861a" stroke="#3a2a22" stroke-width="3" stroke-linejoin="round"/>
      <path d="M184 180q21-10 42 0q-6 22-21 30q-15-8-21-30z" fill="url(#{{ $u }}-beak)" stroke="#3a2a22" stroke-width="3" stroke-linejoin="round"/>

      @if ($dev)
      <!-- headset; the microphone boom is named o-tassel so it sways a little -->
      <g class="o-headset">
        <path d="M96 150a109 109 0 0 1 218 0" fill="none" stroke="#1e293b" stroke-width="11" stroke-linecap="round"/>
        <path d="M96 150a109 109 0 0 1 218 0" fill="none" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
        <rect x="80" y="128" width="30" height="56" rx="12" fill="#1e293b" stroke="#0f172a" stroke-width="3"/>
        <rect x="300" y="128" width="30" height="56" rx="12" fill="#1e293b" stroke="#0f172a" stroke-width="3"/>
        <rect x="86" y="138" width="8" height="36" rx="4" fill="#34e08f"/>
        <rect x="316" y="138" width="8" height="36" rx="4" fill="#34e08f"/>
        <g class="o-tassel">
          <path d="M98 182q4 40 62 40" fill="none" stroke="#1e293b" stroke-width="5" stroke-linecap="round"/>
          <rect x="156" y="213" width="18" height="14" rx="7" fill="#334155" stroke="#0f172a" stroke-width="2.5"/>
        </g>
      </g>
      @else
      <!-- graduation cap -->
      <g class="o-cap" transform="translate(0 -10)">
        <path d="M160 70q45 14 92 0l-2 26q-44 14-88 0z" fill="#1f4f9e" stroke="#13284d" stroke-width="3" stroke-linejoin="round"/>
        <path d="M108 52l98-34 104 32-98 36z" fill="url(#{{ $u }}-cap)" stroke="#13284d" stroke-width="3.5" stroke-linejoin="round"/>
        <circle cx="208" cy="52" r="5" fill="#f4c430"/>
        <g class="o-tassel">
          <path d="M208 52q50 2 92 0v50" fill="none" stroke="#f4c430" stroke-width="3.5" stroke-linecap="round"/>
          <path d="M294 100h12l4 26h-20z" fill="#f4c430" stroke="#b8860b" stroke-width="2" stroke-linejoin="round"/>
        </g>
      </g>
      @endif
    </g>
  </g>
</svg>
