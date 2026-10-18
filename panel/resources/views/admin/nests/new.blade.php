@extends('layouts.admin')

@section('title')
    {{ __('admin/nests.new_title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/nests.new_title') }}<small>{{ __('admin/nests.new_subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nests') }}">{{ __('admin/nests.title') }}</a></li>
        <li class="active">{{ __('admin/nests.new_breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.nests.new') }}" method="POST">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/nests.new_title') }}</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">{{ __('admin/nests.name') }}</label>
                        <div>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" />
                            <p class="text-muted"><small>{{ __('admin/nests.name_help') }}</small></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">{{ __('admin/nests.description') }}</label>
                        <div>
                            <textarea name="description" class="form-control" rows="6">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
