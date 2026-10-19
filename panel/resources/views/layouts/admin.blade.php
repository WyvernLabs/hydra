@php
    $route = Route::currentRouteName();
    $adminUser = Auth::user();
    // Hydra: every entry names the permission needed to see it; overview is open to all admins.
    $navigation = [
        __('admin/common.nav.basic') => [
            ['admin.index', __('admin/common.nav.overview'), 'home', $route === 'admin.index', null],
            ['admin.settings', __('admin/common.nav.settings'), 'cog', starts_with($route, 'admin.settings'), 'settings.view'],
            ['admin.design', __('admin/common.nav.design'), 'color-swatch', starts_with($route, 'admin.design'), 'design.view'],
            ['admin.api.index', __('admin/common.nav.api'), 'code', starts_with($route, 'admin.api'), 'api.view'],
        ],
        __('admin/common.nav.management') => [
            ['admin.databases', __('admin/common.nav.databases'), 'database', starts_with($route, 'admin.databases'), 'databases.view'],
            ['admin.locations', __('admin/common.nav.locations'), 'location-marker', starts_with($route, 'admin.locations'), 'locations.view'],
            ['admin.nodes', __('admin/common.nav.nodes'), 'chip', starts_with($route, 'admin.nodes'), 'nodes.view'],
            ['admin.servers', __('admin/common.nav.servers'), 'server', starts_with($route, 'admin.servers'), 'servers.view'],
            ['admin.users', __('admin/common.nav.users'), 'users', starts_with($route, 'admin.users'), 'users.view'],
            ['admin.roles', __('admin/common.nav.roles'), 'shield-check', starts_with($route, 'admin.roles'), 'roles.view'],
        ],
        __('admin/common.nav.service') => [
            ['admin.mounts', __('admin/common.nav.mounts'), 'link', starts_with($route, 'admin.mounts'), 'mounts.view'],
            ['admin.nests', __('admin/common.nav.nests'), 'collection', starts_with($route, 'admin.nests'), 'nests.view'],
        ],
    ];
    foreach ($navigation as $title => $items) {
        $navigation[$title] = array_filter($items, fn ($item) => is_null($item[4]) || $adminUser->hasAdminPermission($item[4]));
        if (empty($navigation[$title])) {
            unset($navigation[$title]);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Hydra') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        @include('layouts.scripts')

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
        @show

        {{-- Loaded last so that the Hydra theme wins over AdminLTE and Bootstrap. --}}
        @include('partials.hydra.head')
        <link rel="stylesheet" href="/hydra/admin.css?v={{ config('app.version') }}">
    </head>
    <body class="hydra-body hydra-admin">
        <div class="hydra-shell">
            <header class="hydra-topbar">
                <button type="button" class="hydra-icon-btn" id="hydraMenuToggle">
                    <x-hydra.icon name="menu" />
                    <span class="sr-only">{{ __('admin/common.nav.menu') }}</span>
                </button>
                <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Hydra') }}" class="hydra-topbar-logo">
            </header>
            <div class="hydra-backdrop" id="hydraBackdrop" hidden></div>
            <aside class="hydra-sidebar" id="hydraSidebar">
                <a href="{{ route('admin.index') }}" class="hydra-sidebar-logo">
                    <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Hydra') }}">
                </a>
                <nav class="hydra-nav">
                    @foreach($navigation as $title => $items)
                        <div class="hydra-nav-section">
                            <div class="hydra-nav-title">{{ $title }}</div>
                            @foreach($items as [$name, $label, $icon, $active])
                                <a href="{{ route($name) }}" class="hydra-nav-item @if($active) active @endif">
                                    <x-hydra.icon :name="$icon" />
                                    <span>{{ $label }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
                <div class="hydra-sidebar-footer">
                    <a href="{{ route('account') }}" class="hydra-icon-btn" data-toggle="tooltip" data-placement="top" title="{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}">
                        <x-hydra.icon name="user-circle" />
                    </a>
                    <a href="{{ route('index') }}" class="hydra-icon-btn" data-toggle="tooltip" data-placement="top" title="{{ __('admin/common.nav.exit') }}">
                        <x-hydra.icon name="view-grid" />
                    </a>
                    <a href="{{ route('auth.logout') }}" class="hydra-icon-btn" id="logoutButton" data-toggle="tooltip" data-placement="top" title="{{ __('admin/common.nav.logout') }}">
                        <x-hydra.icon name="logout" />
                    </a>
                </div>
            </aside>
            <main class="hydra-main hydra-admin-main">
                <section class="content-header">
                    @yield('content-header')
                </section>
                <section class="content">
                    <div class="row">
                        <div class="col-xs-12">
                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    {{ __('admin/common.validation_error') }}<br><br>
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @foreach (Alert::getMessages() as $type => $messages)
                                @foreach ($messages as $message)
                                    <div class="alert alert-{{ $type }} alert-dismissable" role="alert">
                                        {{ $message }}
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    @yield('content')
                </section>
                <footer class="hydra-footer">
                    {{ __('admin/common.footer', ['name' => config('app.name', 'Hydra'), 'year' => date('Y'), 'version' => $appVersion]) }}
                    &middot; {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </footer>
            </main>
        </div>
        @section('footer-scripts')
            <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
            <script>keyboardeventKeyPolyfill.polyfill();</script>

            <script>
                // Hydra: texts for the admin scripts in public/themes/pterodactyl/js/admin.
                window.HydraLang = {
                    required: @json(__('admin/servers.fields.required')),
                    selectUser: @json(__('admin/servers.fields.select_user')),
                    selectNest: @json(__('admin/servers.fields.select_nest')),
                    selectEgg: @json(__('admin/servers.fields.select_egg')),
                    selectNode: @json(__('admin/servers.fields.select_node')),
                    selectDefaultAllocation: @json(__('admin/servers.fields.select_default_allocation')),
                    selectAdditionalAllocations: @json(__('admin/servers.fields.select_additional_allocations')),
                    accessInStartup: @json(__('admin/servers.fields.access_in_startup')),
                    validationRules: @json(__('admin/servers.fields.validation_rules')),
                };
                window.hydraLang = function (key, fallback) {
                    return (window.HydraLang && window.HydraLang[key]) || fallback;
                };
            </script>
            {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
            {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
            <script src="/js/autocomplete.js" type="application/javascript"></script>

            @if(Auth::user()->isAdmin())
                <script>
                    $('#logoutButton').on('click', function (event) {
                        event.preventDefault();

                        var that = this;
                        swal({
                            title: @json(__('admin/common.logout_confirm')),
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d9534f',
                            cancelButtonColor: '#d33',
                            confirmButtonText: @json(__('admin/common.nav.logout')),
                            cancelButtonText: @json(__('admin/common.actions.cancel'))
                        }, function () {
                             $.ajax({
                                type: 'POST',
                                url: '{{ route('auth.logout') }}',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },complete: function () {
                                    window.location.href = '{{route('auth.login')}}';
                                }
                        });
                    });
                });
                </script>
            @endif

            <script>
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip();

                    // Off-canvas sidebar on small screens, same behaviour as the client area.
                    var sidebar = document.getElementById('hydraSidebar');
                    var backdrop = document.getElementById('hydraBackdrop');
                    function toggleMenu(open) {
                        sidebar.classList.toggle('hydra-sidebar--open', open);
                        backdrop.hidden = !open;
                    }
                    $('#hydraMenuToggle').on('click', function () {
                        toggleMenu(!sidebar.classList.contains('hydra-sidebar--open'));
                    });
                    $(backdrop).on('click', function () { toggleMenu(false); });
                })
            </script>
        @show
    </body>
</html>
