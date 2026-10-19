@include('partials/admin.settings.notice')

@section('settings::nav')
    @yield('settings::notice')
    <div class="row">
        <div class="col-xs-12">
            <div class="nav-tabs-custom nav-tabs-floating">
                <ul class="nav nav-tabs">
                    <li @if($activeTab === 'basic')class="active"@endif><a href="{{ route('admin.settings') }}">{{ __('admin/settings.tabs.general') }}</a></li>
                    <li @if($activeTab === 'mail')class="active"@endif><a href="{{ route('admin.settings.mail') }}">{{ __('admin/settings.tabs.mail') }}</a></li>
                    <li @if($activeTab === 'advanced')class="active"@endif><a href="{{ route('admin.settings.advanced') }}">{{ __('admin/settings.tabs.advanced') }}</a></li>
                    @if(Auth::user()->hasAdminPermission('updates.view'))
                        <li @if($activeTab === 'updates')class="active"@endif><a href="{{ route('admin.settings.updates') }}">{{ __('admin/settings.tabs.updates') }}</a></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
@endsection
