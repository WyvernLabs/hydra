{{-- Hydra: tabs of the "Design" area. Expects $activeTab. --}}
@section('design::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="nav-tabs-custom nav-tabs-floating">
                <ul class="nav nav-tabs">
                    <li @if($activeTab === 'general')class="active"@endif><a href="{{ route('admin.design') }}">{{ __('admin/design.tabs.general') }}</a></li>
                    <li @if($activeTab === 'backgrounds')class="active"@endif><a href="{{ route('admin.design.backgrounds') }}">{{ __('admin/design.tabs.backgrounds') }}</a></li>
                    <li @if($activeTab === 'links')class="active"@endif><a href="{{ route('admin.design.links') }}">{{ __('admin/design.tabs.links') }}</a></li>
                    @if(Auth::user()->hasAdminPermission('addons.view'))
                        <li @if($activeTab === 'addons')class="active"@endif><a href="{{ route('admin.design.addons') }}">{{ __('admin/design.tabs.addons') }}</a></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
@endsection
