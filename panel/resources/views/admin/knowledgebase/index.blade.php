@extends('layouts.admin')

@php($canEdit = Auth::user()->hasAdminPermission('knowledgebase.update'))

@section('hydra-sidebar')
    @include('admin.knowledgebase.partials.sidebar')
@endsection

@section('title')
    {{ __('admin/knowledgebase.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/knowledgebase.title') }}<small>{{ __('admin/knowledgebase.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/knowledgebase.title') }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        @foreach(['categories' => 'folder', 'subcategories' => 'folder-plus', 'posts' => 'file-text', 'drafts' => 'pencil'] as $key => $icon)
            <div class="col-sm-6 col-lg-3">
                <div class="box hydra-kb-stat">
                    <div class="box-body">
                        <x-hydra.icon :name="$icon" />
                        <div>
                            <strong>{{ $counts[$key] }}</strong>
                            <span>{{ __("admin/knowledgebase.stats.$key") }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="row">
        @if($canEdit)
            <div class="col-md-6">
                <div class="box box-primary" id="new-category">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/knowledgebase.new_category') }}</h3>
                    </div>
                    <form action="{{ route('admin.knowledgebase.categories') }}" method="POST">
                        <div class="box-body">
                            @include('admin.knowledgebase.partials.category-form')
                        </div>
                        <div class="box-footer">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/knowledgebase.create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/knowledgebase.howto_title') }}</h3>
                </div>
                <div class="box-body">
                    <ul class="hydra-kb-howto">
                        @foreach(__('admin/knowledgebase.howto') as $line)
                            <li>{!! $line !!}</li>
                        @endforeach
                    </ul>
                    <a href="{{ $addon->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-default">
                        <x-hydra.icon name="external-link" /> {{ __('admin/knowledgebase.open_public') }} ({{ $addon->url() }})
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
