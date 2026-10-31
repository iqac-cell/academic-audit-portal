{{-- Scrolling notice bar. Pass $notices (collection of Notice). Plain CSS so it also works on the login page. --}}
@if(isset($notices) && $notices->isNotEmpty())
    @php($chars = $notices->sum(fn ($n) => mb_strlen($n->title) + 12))
    <div class="aa-ticker" role="region" aria-label="Notices">
        <div class="aa-ticker-label"><i class="bi bi-megaphone-fill"></i> Notices</div>
        <div class="aa-ticker-track">
            <div class="aa-ticker-move" style="animation-duration: {{ max(15, (int) round($chars / 6)) }}s">
                @foreach($notices as $n)
                    <span class="aa-ticker-item">
                        @if($n->is_new)<span class="aa-new">NEW</span>@endif
                        @if($url = $n->url())
                            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $n->title }}@if($n->attachment_path) <i class="bi bi-paperclip"></i>@endif</a>
                        @else
                            {{ $n->title }}
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    </div>
    <style>
        .aa-ticker { display: flex; align-items: stretch; background: #fff8e1; border: 1px solid #f0d58c; border-radius: .375rem; overflow: hidden; font-size: .9rem; margin-bottom: 1rem; }
        .aa-ticker-label { background: #b45309; color: #fff; padding: .45rem .75rem; font-weight: 600; white-space: nowrap; flex: 0 0 auto; }
        .aa-ticker-track { overflow: hidden; flex: 1 1 auto; position: relative; }
        .aa-ticker-move { display: inline-block; white-space: nowrap; padding-left: 100%; padding-top: .45rem; padding-bottom: .45rem; animation: aa-ticker-scroll linear infinite; }
        .aa-ticker:hover .aa-ticker-move, .aa-ticker:focus-within .aa-ticker-move { animation-play-state: paused; }
        .aa-ticker-item { margin-right: 3rem; color: #5c3d00; }
        .aa-ticker-item::before { content: "◆"; color: #b45309; margin-right: .5rem; font-size: .7em; vertical-align: middle; }
        .aa-ticker-item a { color: #8a3b00; font-weight: 500; }
        .aa-new { background: #dc3545; color: #fff; font-size: .65rem; font-weight: 700; padding: .05rem .3rem; border-radius: .2rem; margin-right: .3rem; animation: aa-blink 1s steps(2, start) infinite; }
        @keyframes aa-ticker-scroll { from { transform: translateX(0); } to { transform: translateX(-100%); } }
        @keyframes aa-blink { to { visibility: hidden; } }
        @media (prefers-reduced-motion: reduce) {
            .aa-ticker-move { animation: none; padding-left: .75rem; white-space: normal; }
            .aa-ticker-item { display: block; margin: 0; }
            .aa-new { animation: none; }
        }
    </style>
@endif
