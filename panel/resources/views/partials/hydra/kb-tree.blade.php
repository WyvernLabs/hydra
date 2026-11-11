{{--
    Hydra: tree of the knowledgebase (categories > subcategories > posts) with
    folding, used in the admin area and on the knowledgebase itself.
    Expects $tree, $postUrl (fn (post) => url), optionally $categoryUrl
    (fn (category) => url), $activePost, $activeCategory and $storageKey.
    Opened nodes are remembered in localStorage (see kb-tree script below).
--}}
@php
    $activePostId = isset($activePost) && $activePost ? $activePost->id : null;
    $activeCategoryId = isset($activeCategory) && $activeCategory ? $activeCategory->id : null;
    $categoryUrl = $categoryUrl ?? null;
    $contains = function ($category) use ($activePostId, $activeCategoryId) {
        if ($category->id === $activeCategoryId || $category->posts->contains('id', $activePostId)) return true;
        foreach ($category->children as $child) {
            if ($child->id === $activeCategoryId || $child->posts->contains('id', $activePostId)) return true;
        }
        return false;
    };
@endphp
<nav class="hydra-kb-tree" data-kb-tree="{{ $storageKey ?? 'hydra:kb-tree' }}">
    @forelse($tree as $category)
        <details class="hydra-kb-node" data-kb-node="{{ $category->id }}" @if($contains($category)) open data-kb-force @endif>
            <summary class="hydra-kb-summary @if($category->id === $activeCategoryId) active @endif">
                <x-hydra.icon name="chevron-right" class="hydra-kb-chevron" />
                @if($categoryUrl)
                    <a href="{{ $categoryUrl($category) }}">{{ $category->name() }}</a>
                @else
                    <span>{{ $category->name() }}</span>
                @endif
            </summary>
            <div class="hydra-kb-children">
                @foreach($category->posts as $post)
                    <a href="{{ $postUrl($post) }}" class="hydra-kb-post @if($post->id === $activePostId) active @endif @unless($post->published) is-draft @endunless">
                        <x-hydra.icon name="file-text" />
                        <span>{{ $post->title() }}</span>
                    </a>
                @endforeach
                @foreach($category->children as $child)
                    <details class="hydra-kb-node is-sub" data-kb-node="{{ $child->id }}" @if($child->id === $activeCategoryId || $child->posts->contains('id', $activePostId)) open data-kb-force @endif>
                        <summary class="hydra-kb-summary @if($child->id === $activeCategoryId) active @endif">
                            <x-hydra.icon name="chevron-right" class="hydra-kb-chevron" />
                            @if($categoryUrl)
                                <a href="{{ $categoryUrl($child) }}">{{ $child->name() }}</a>
                            @else
                                <span>{{ $child->name() }}</span>
                            @endif
                        </summary>
                        <div class="hydra-kb-children">
                            @forelse($child->posts as $post)
                                <a href="{{ $postUrl($post) }}" class="hydra-kb-post @if($post->id === $activePostId) active @endif @unless($post->published) is-draft @endunless">
                                    <x-hydra.icon name="file-text" />
                                    <span>{{ $post->title() }}</span>
                                </a>
                            @empty
                                <span class="hydra-kb-empty">{{ __('strings.kb.no_posts') }}</span>
                            @endforelse
                        </div>
                    </details>
                @endforeach
                @if($category->posts->isEmpty() && $category->children->isEmpty())
                    <span class="hydra-kb-empty">{{ __('strings.kb.no_posts') }}</span>
                @endif
            </div>
        </details>
    @empty
        <p class="hydra-kb-empty">{{ __('strings.kb.no_categories') }}</p>
    @endforelse
</nav>
<script>
    (function () {
        var tree = document.currentScript.previousElementSibling;
        var key = tree.getAttribute('data-kb-tree');
        var open = {};
        try { open = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) { open = {}; }
        tree.querySelectorAll('details[data-kb-node]').forEach(function (node) {
            var id = node.getAttribute('data-kb-node');
            if (!node.hasAttribute('data-kb-force') && open[id]) node.open = true;
            node.addEventListener('toggle', function () {
                if (node.open) { open[id] = 1; } else { delete open[id]; }
                try { localStorage.setItem(key, JSON.stringify(open)); } catch (e) { /* storage blocked */ }
            });
        });
        // A click on the name opens the page without folding the node.
        tree.querySelectorAll('summary a').forEach(function (link) {
            link.addEventListener('click', function (event) { event.stopPropagation(); });
        });
    })();
</script>
