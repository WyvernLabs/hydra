@extends('layouts.admin')

@section('title')
    {{ __('admin/sanctions.templates.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/sanctions.templates.title') }}<small>{{ __('admin/sanctions.templates.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.users') }}">{{ __('admin/users.title') }}</a></li>
        <li class="active">{{ __('admin/sanctions.templates.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        @forelse($templates as $template)
            <form action="{{ route('admin.users.ban-templates.update', $template->id) }}" method="POST" class="box">
                {!! csrf_field() !!}
                {!! method_field('PATCH') !!}
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-sm-8">
                            <label class="control-label">{{ __('admin/sanctions.templates.name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ $template->name }}" maxlength="191" required>
                        </div>
                        <div class="form-group col-sm-4">
                            <label class="control-label">{{ __('admin/sanctions.duration') }}</label>
                            <div class="input-group">
                                <input type="number" name="duration_days" class="form-control" min="1" max="3650" value="{{ $template->duration_days }}" placeholder="{{ __('admin/sanctions.permanent') }}">
                                <span class="input-group-addon">{{ __('admin/sanctions.days_unit') }}</span>
                            </div>
                        </div>
                        <div class="form-group col-xs-12 no-margin-bottom">
                            <label class="control-label">{{ __('admin/sanctions.reason') }}</label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="2000" required>{{ $template->reason }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    <button type="submit" form="delete-template-{{ $template->id }}" class="btn btn-sm btn-danger">{{ __('admin/sanctions.templates.delete') }}</button>
                </div>
            </form>
            <form id="delete-template-{{ $template->id }}" action="{{ route('admin.users.ban-templates.delete', $template->id) }}" method="POST" hidden>
                {!! csrf_field() !!}
                {!! method_field('DELETE') !!}
            </form>
        @empty
            <div class="box"><div class="box-body text-muted">{{ __('admin/sanctions.templates.none') }}</div></div>
        @endforelse
    </div>
    <div class="col-md-4">
        <form action="{{ route('admin.users.ban-templates') }}" method="POST" class="box box-primary">
            {!! csrf_field() !!}
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/sanctions.templates.new') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label class="control-label" for="templateName">{{ __('admin/sanctions.templates.name') }}</label>
                    <input type="text" id="templateName" name="name" class="form-control" maxlength="191" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label class="control-label" for="templateReason">{{ __('admin/sanctions.reason') }}</label>
                    <textarea id="templateReason" name="reason" class="form-control" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea>
                    <p class="text-muted small">{{ __('admin/sanctions.templates.reason_help') }}</p>
                </div>
                <div class="form-group no-margin-bottom">
                    <label class="control-label" for="templateDays">{{ __('admin/sanctions.duration') }}</label>
                    <div class="input-group">
                        <input type="number" id="templateDays" name="duration_days" class="form-control" min="1" max="3650" value="{{ old('duration_days') }}" placeholder="{{ __('admin/sanctions.permanent') }}">
                        <span class="input-group-addon">{{ __('admin/sanctions.days_unit') }}</span>
                    </div>
                    <p class="text-muted small">{{ __('admin/sanctions.templates.duration_help') }}</p>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/sanctions.templates.create') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
