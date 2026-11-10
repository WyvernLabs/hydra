{{-- Hydra: the news of every release, newest first. Expects $news (NewsService::overview). --}}
@php($locale = app()->getLocale())
@if($news['error'])
    <div class="hydra-news-error">
        <x-hydra.icon name="cloud-off" />
        {{ __('admin/overview.news.unreachable') }}
    </div>
@endif
@forelse($news['releases'] as $index => $release)
    <details class="hydra-news-release @if($release['is_newer']) is-newer @endif @if($release['is_installed']) is-installed @endif" @if($index === 0 || $release['is_newer'] || $release['is_installed']) open @endif>
        <summary>
            <span class="hydra-news-version">{{ $release['version'] }}</span>
            <span class="hydra-news-title">{{ \Pterodactyl\Services\Hydra\NewsService::text($release['title'], $locale) }}</span>
            @if($release['is_newer'])
                <span class="hydra-news-badge is-new">{{ __('admin/overview.news.available') }}</span>
            @elseif($release['is_installed'])
                <span class="hydra-news-badge is-installed">{{ __('admin/overview.news.installed') }}</span>
            @endif
            <span class="hydra-news-meta">
                {{ __('admin/overview.build', ['build' => $release['build']]) }}
                @if($release['date'])
                    · {{ \Illuminate\Support\Carbon::parse($release['date'])->locale($locale)->isoFormat('LL') }}
                @endif
            </span>
        </summary>
        <ul class="hydra-news-changes">
            @foreach($release['changes'] as $change)
                <li>
                    <span class="hydra-news-type is-{{ $change['type'] }}">{{ __('admin/overview.news.types.' . $change['type']) }}</span>
                    <span>{!! preg_replace('/`([^`]+)`/', '<code>$1</code>', e(\Pterodactyl\Services\Hydra\NewsService::text($change['text'], $locale))) !!}</span>
                </li>
            @endforeach
        </ul>
    </details>
@empty
    <p class="text-muted">{{ __('admin/overview.news.empty') }}</p>
@endforelse
