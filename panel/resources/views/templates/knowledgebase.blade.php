{{--
    Hydra: the knowledgebase for users (addon "knowledgebase"). Own sidebar with
    the categories, subcategories and posts (foldable) and a search; on the
    right the post or, without one, an overview of the categories.
--}}
@php($title = $post ? $post->title() : __('strings.kb.title'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1" name="viewport">
        <title>{{ $title }} – {{ config('app.name', 'Hydra') }}</title>
        @include('partials.hydra.head')
    </head>
    <body class="hydra-body hydra-kb-page">
        @include('partials.hydra.backdrop', ['background' => $background])
        <div class="hydra-shell">
            <header class="hydra-topbar">
                <button type="button" class="hydra-icon-btn" id="hydraMenuToggle">
                    <x-hydra.icon name="menu" />
                    <span class="sr-only">{{ __('strings.kb.title') }}</span>
                </button>
                <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Hydra') }}" class="hydra-topbar-logo {{ $branding->protectedClass() }}" draggable="false">
            </header>
            <div class="hydra-backdrop" id="hydraBackdrop" hidden></div>
            <aside class="hydra-sidebar hydra-kb-sidebar" id="hydraSidebar">
                <a href="{{ $base }}" class="hydra-sidebar-logo">
                    <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Hydra') }}" class="{{ $branding->protectedClass() }}" draggable="false">
                </a>
                <div class="hydra-kb-sidebar-head">
                    <a href="{{ $base }}" class="hydra-kb-sidebar-name"><x-hydra.icon name="book-open" /> {{ __('strings.kb.title') }}</a>
                    @auth
                        <a href="/" class="hydra-designer-back"><x-hydra.icon name="arrow-left" /><span>{{ __('strings.kb.back') }}</span></a>
                    @else
                        <a href="{{ route('auth.login') }}" class="hydra-designer-back"><x-hydra.icon name="log-in" /><span>{{ __('strings.kb.login') }}</span></a>
                    @endauth
                    <label class="hydra-kb-search">
                        <x-hydra.icon name="search" />
                        <input type="search" id="hydraKbSearch" placeholder="{{ __('strings.kb.search') }}" autocomplete="off">
                    </label>
                </div>
                <div class="hydra-kb-sidebar-tree">
                    @include('partials.hydra.kb-tree', [
                        'tree' => $tree,
                        'postUrl' => fn ($p) => $base . '/' . $p->slug,
                        'activePost' => $post,
                        'storageKey' => 'hydra:kb',
                    ])
                    <p class="hydra-kb-empty" id="hydraKbNoResults" hidden>{{ __('strings.kb.no_results') }}</p>
                </div>
            </aside>
            <main class="hydra-main hydra-kb-main">
                @if($post)
                    <article class="hydra-box hydra-kb-article">
                        <div class="hydra-box-body">
                            <div class="hydra-kb-crumbs">
                                @if($post->category->parent)
                                    <span>{{ $post->category->parent->name() }}</span>
                                    <x-hydra.icon name="chevron-right" />
                                @endif
                                <span>{{ $post->category->name() }}</span>
                            </div>
                            <h1 class="hydra-kb-title">{{ $post->title() }}</h1>
                            <p class="hydra-kb-meta">{{ __('strings.kb.updated', ['date' => $post->updated_at->timezone(config('app.timezone'))->translatedFormat('j. F Y')]) }}</p>
                            <div class="hydra-markdown hydra-kb-content">{!! $html !!}</div>
                        </div>
                    </article>
                    @if($previous || $next)
                        <nav class="hydra-kb-pager">
                            @if($previous)
                                <a href="{{ $base }}/{{ $previous->slug }}" class="hydra-box is-previous">
                                    <small>{{ __('strings.kb.previous') }}</small>
                                    <span>{{ $previous->title() }}</span>
                                </a>
                            @else
                                <span></span>
                            @endif
                            @if($next)
                                <a href="{{ $base }}/{{ $next->slug }}" class="hydra-box is-next">
                                    <small>{{ __('strings.kb.next') }}</small>
                                    <span>{{ $next->title() }}</span>
                                </a>
                            @endif
                        </nav>
                    @endif
                @else
                    <div class="hydra-box hydra-kb-hero">
                        <div class="hydra-box-body">
                            <h1 class="hydra-kb-title">{{ __('strings.kb.welcome') }}</h1>
                            <p class="hydra-kb-meta">{{ __('strings.kb.welcome_text') }}</p>
                        </div>
                    </div>
                    <div class="hydra-kb-grid">
                        @forelse($tree as $category)
                            @php($count = $category->posts->count() + $category->children->sum(fn ($c) => $c->posts->count()))
                            <section class="hydra-box hydra-kb-card">
                                <div class="hydra-box-body">
                                    <h2><x-hydra.icon name="folder" /> {{ $category->name() }}</h2>
                                    <p class="hydra-kb-meta">{{ trans_choice('strings.kb.posts', $count) }}</p>
                                    <ul>
                                        @foreach($category->posts->take(5) as $item)
                                            <li><a href="{{ $base }}/{{ $item->slug }}">{{ $item->title() }}</a></li>
                                        @endforeach
                                        @foreach($category->children as $child)
                                            @if($child->posts->isNotEmpty())
                                                <li class="is-sub"><span>{{ $child->name() }}</span></li>
                                                @foreach($child->posts->take(3) as $item)
                                                    <li><a href="{{ $base }}/{{ $item->slug }}">{{ $item->title() }}</a></li>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            </section>
                        @empty
                            <p class="hydra-kb-empty">{{ __('strings.kb.no_categories') }}</p>
                        @endforelse
                    </div>
                @endif
                <p class="hydra-footer">@include('partials.hydra.credit')</p>
            </main>
        </div>
        <script>
            (function () {
                // Off-canvas sidebar on small screens.
                var sidebar = document.getElementById('hydraSidebar');
                var backdrop = document.getElementById('hydraBackdrop');
                function toggle(open) { sidebar.classList.toggle('hydra-sidebar--open', open); backdrop.hidden = !open; }
                document.getElementById('hydraMenuToggle').addEventListener('click', function () { toggle(!sidebar.classList.contains('hydra-sidebar--open')); });
                backdrop.addEventListener('click', function () { toggle(false); });

                // Search: filters the posts of the tree and opens the categories with hits.
                var input = document.getElementById('hydraKbSearch');
                var tree = sidebar.querySelector('.hydra-kb-tree');
                var none = document.getElementById('hydraKbNoResults');
                input.addEventListener('input', function () {
                    var query = input.value.trim().toLowerCase();
                    var hits = 0;
                    tree.querySelectorAll('.hydra-kb-post').forEach(function (link) {
                        var match = !query || link.textContent.toLowerCase().indexOf(query) >= 0;
                        link.hidden = !match;
                        if (match) hits++;
                    });
                    tree.querySelectorAll('details').forEach(function (node) {
                        var visible = !query || node.querySelector('.hydra-kb-post:not([hidden])');
                        node.hidden = !visible;
                        if (query && visible) node.open = true;
                    });
                    tree.querySelectorAll('.hydra-kb-empty').forEach(function (empty) { empty.hidden = !!query; });
                    none.hidden = !query || hits > 0;
                });

                // Spoilers open on click, like on Discord.
                document.addEventListener('click', function (event) {
                    if (event.target.classList && event.target.classList.contains('md-spoiler')) event.target.classList.add('is-revealed');
                });
            })();
        </script>
    </body>
</html>
