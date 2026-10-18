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
        <div class="box
            @if($version->isLatestPanel())
                box-success
            @else
                box-danger
            @endif
        ">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/overview.system') }}</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    {!! __('admin/overview.up_to_date', ['version' => e(config('app.version')), 'upstream' => e(config('hydra.upstream.panel'))]) !!}
                @else
                    {!! __('admin/overview.outdated', ['latest' => e($version->getPanel()), 'version' => e(config('app.version'))]) !!}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
