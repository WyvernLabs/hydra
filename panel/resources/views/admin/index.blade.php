@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
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
                <h3 class="box-title">System Information</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    You are running Hydra Panel version <code>{{ config('app.version') }}</code>, based on Pterodactyl Panel <code>{{ config('hydra.upstream.panel') }}</code>.
                @else
                    Your panel is <strong>not up-to-date!</strong> The latest version is <code>{{ $version->getPanel() }}</code> and you are currently running version <code>{{ config('app.version') }}</code>. Run <code>hydra-panel update</code> on the panel server to update.
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
