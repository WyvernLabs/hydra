@extends('layouts.admin')

@php($canEdit = Auth::user()->hasAdminPermission('knowledgebase.update'))
@php($canDelete = Auth::user()->hasAdminPermission('knowledgebase.delete'))

@section('hydra-sidebar')
    @include('admin.knowledgebase.partials.sidebar')
@endsection

@section('title')
    {{ $category->name() }} – {{ __('admin/knowledgebase.title') }}
@endsection

@section('content-header')
    <h1>{{ $category->name() }}<small>{{ $category->parent ? __('admin/knowledgebase.subcategory_of', ['name' => $category->parent->name()]) : __('admin/knowledgebase.main_category') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.knowledgebase') }}">{{ __('admin/knowledgebase.title') }}</a></li>
        @if($category->parent)
            <li><a href="{{ route('admin.knowledgebase.category', $category->parent->id) }}">{{ $category->parent->name() }}</a></li>
        @endif
        <li class="active">{{ $category->name() }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-7">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/knowledgebase.posts_in') }}</h3>
                    @if($canEdit)
                        <div class="box-tools">
                            <a href="{{ route('admin.knowledgebase.posts.new', ['category' => $category->id]) }}" class="btn btn-sm btn-primary">
                                <x-hydra.icon name="file-plus" /> {{ __('admin/knowledgebase.new_post') }}
                            </a>
                        </div>
                    @endif
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            @forelse($category->posts as $item)
                                <tr>
                                    <td><a href="{{ route('admin.knowledgebase.post', $item->id) }}">{{ $item->title() }}</a></td>
                                    <td class="text-right">
                                        @unless($item->published)<span class="label label-warning">{{ __('strings.kb.draft') }}</span>@endunless
                                        <span class="text-muted small">{{ $item->updated_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-muted text-center">{{ __('strings.kb.no_posts') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @unless($category->parent_id)
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/knowledgebase.subcategories') }}</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <tbody>
                                @forelse($category->children as $child)
                                    <tr>
                                        <td><a href="{{ route('admin.knowledgebase.category', $child->id) }}">{{ $child->name() }}</a></td>
                                        <td class="text-right text-muted small">{{ trans_choice('strings.kb.posts', $child->posts()->count()) }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted text-center">{{ __('admin/knowledgebase.no_subcategories') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($canEdit)
                        <form action="{{ route('admin.knowledgebase.categories') }}" method="POST" class="box-footer hydra-kb-inline-form">
                            {!! csrf_field() !!}
                            <input type="hidden" name="parent_id" value="{{ $category->id }}">
                            <input type="text" name="names[{{ $defaultLanguage }}]" class="form-control input-sm" maxlength="80" required
                                   placeholder="{{ __('admin/knowledgebase.new_subcategory_placeholder', ['language' => $languages[$defaultLanguage] ?? $defaultLanguage]) }}">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('admin/knowledgebase.add_subcategory') }}</button>
                        </form>
                    @endif
                </div>
            @endunless
        </div>
        <div class="col-md-5">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/knowledgebase.edit_category') }}</h3>
                </div>
                <form action="{{ route('admin.knowledgebase.category', $category->id) }}" method="POST">
                    <fieldset @disabled(!$canEdit)>
                        <div class="box-body">
                            @include('admin.knowledgebase.partials.category-form')
                        </div>
                    </fieldset>
                    @if($canEdit)
                        <div class="box-footer">
                            {!! csrf_field() !!}
                            {!! method_field('PATCH') !!}
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                        </div>
                    @endif
                </form>
            </div>
            @if($canDelete)
                <div class="box box-danger">
                    <div class="box-body">
                        <p class="text-muted small">{{ $category->parent_id ? __('admin/knowledgebase.delete_subcategory_help') : __('admin/knowledgebase.delete_category_help') }}</p>
                        <form action="{{ route('admin.knowledgebase.category', $category->id) }}" method="POST" data-kb-confirm="{{ __('admin/knowledgebase.delete_confirm') }}">
                            {!! csrf_field() !!}
                            {!! method_field('DELETE') !!}
                            <button type="submit" class="btn btn-sm btn-danger"><x-hydra.icon name="trash" /> {{ __('admin/knowledgebase.delete_category') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    @include('admin.knowledgebase.partials.confirm')
@endsection
