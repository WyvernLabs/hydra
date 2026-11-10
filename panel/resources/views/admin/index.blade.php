@extends('layouts.admin')

@section('title')
    {{ __('admin/overview.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/overview.heading') }}<small>{{ __('admin/overview.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/overview.breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/overview.system') }}</h3>
            </div>
            <div class="box-body" id="hydraVersion">
                @include('partials.admin.hydra.version', ['news' => $news])
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box" id="hydraNews">
            <div class="box-header with-border hydra-news-header">
                <h3 class="box-title"><x-hydra.icon name="newspaper" /> {{ __('admin/overview.news.title') }}</h3>
                <div class="box-tools">
                    <span class="text-muted hydra-news-checked" data-news-checked data-checked-at="{{ $news['checked_at'] }}"></span>
                    <button type="button" class="btn btn-xs btn-default" data-news-refresh title="{{ __('admin/overview.news.refresh') }}">
                        <x-hydra.icon name="refresh-cw" /> {{ __('admin/overview.news.refresh') }}
                    </button>
                </div>
            </div>
            <div class="box-body" data-news-body>
                @include('partials.admin.hydra.news', ['news' => $news])
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        // Hydra: reload the news every {{ $interval }} minutes and on the button.
        (function () {
            var box = document.getElementById('hydraNews');
            var body = box.querySelector('[data-news-body]');
            var button = box.querySelector('[data-news-refresh]');
            var checked = box.querySelector('[data-news-checked]');
            var version = document.getElementById('hydraVersion');
            var interval = {{ $interval }} * 60 * 1000;
            var labels = {
                checked: @json(__('admin/overview.news.checked')),
                never: @json(__('admin/overview.news.never')),
                failed: @json(__('admin/overview.news.failed'))
            };
            var timer;

            function showChecked(iso) {
                if (!iso) {
                    checked.textContent = labels.never;
                    return;
                }
                var time = new Date(iso).toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' });
                checked.textContent = labels.checked.replace(':time', time);
            }

            function load(refresh) {
                button.disabled = true;
                button.classList.add('is-loading');
                fetch(@json(route('admin.index.news')) + (refresh ? '?refresh=1' : ''), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) throw new Error(response.status);
                    return response.json();
                }).then(function (data) {
                    // Keep releases the admin opened or closed as they were.
                    var open = {};
                    body.querySelectorAll('details').forEach(function (item) {
                        open[item.querySelector('.hydra-news-version').textContent] = item.open;
                    });
                    body.innerHTML = data.html;
                    version.innerHTML = data.system;
                    body.querySelectorAll('details').forEach(function (item) {
                        var key = item.querySelector('.hydra-news-version').textContent;
                        if (key in open && !item.classList.contains('is-newer')) item.open = open[key];
                    });
                    showChecked(data.checked_at);
                }).catch(function () {
                    checked.textContent = labels.failed;
                }).finally(function () {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    schedule();
                });
            }

            function schedule() {
                clearTimeout(timer);
                timer = setTimeout(function () { load(false); }, interval);
            }

            button.addEventListener('click', function () { load(true); });
            showChecked(checked.dataset.checkedAt);
            schedule();
        })();
    </script>
@endsection
