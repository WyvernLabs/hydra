{{-- Hydra: tabs of the API pages, $active is "keys" or "docs". --}}
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li @if($active === 'keys') class="active" @endif><a href="{{ route('admin.api.index') }}">{{ __('admin/api_docs.tabs.keys') }}</a></li>
                <li @if($active === 'docs') class="active" @endif><a href="{{ route('admin.api.docs') }}">{{ __('admin/api_docs.tabs.docs') }}</a></li>
            </ul>
        </div>
    </div>
</div>
