{{-- Hydra: installed version and build, and whether a newer release exists. Expects $news (NewsService::overview). --}}
<div class="hydra-version">
    <div class="hydra-version-main">
        <span class="hydra-version-label">{{ __('admin/overview.installed') }}</span>
        <strong class="hydra-version-number">Hydra Panel {{ $news['version'] }}</strong>
        <span class="hydra-version-build">{{ __('admin/overview.build', ['build' => $news['build']]) }}</span>
    </div>
    <p class="text-muted no-margin">{!! __('admin/overview.based_on', ['upstream' => e(config('hydra.upstream.panel'))]) !!}</p>
    @if($news['has_update'])
        <div class="hydra-version-update">
            <x-hydra.icon name="circle-arrow-up" />
            <span>{!! __('admin/overview.update_available', ['version' => e($news['latest']['version']), 'build' => (int) $news['latest']['build']]) !!}</span>
            @if(Auth::user()->hasAdminPermission('updates.view'))
                <a href="{{ route('admin.settings.updates') }}" class="btn btn-xs btn-primary">{{ __('admin/overview.to_updates') }}</a>
            @else
                <span class="text-muted">{!! __('admin/overview.update_command') !!}</span>
            @endif
        </div>
    @else
        <div class="hydra-version-current">
            <x-hydra.icon name="circle-check" />
            <span>{{ __('admin/overview.up_to_date') }}</span>
        </div>
    @endif
</div>
