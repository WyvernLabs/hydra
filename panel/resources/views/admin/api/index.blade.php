@extends('layouts.admin')

@section('title')
    {{ __('admin/api.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/api.title') }}<small>{{ __('admin/api.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/api.title') }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/api.list') }}</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.api.new') }}" class="btn btn-sm btn-primary">{{ __('admin/common.actions.create_new') }}</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tr>
                            <th>{{ __('admin/api.key') }}</th>
                            <th>{{ __('admin/api.memo') }}</th>
                            <th>{{ __('admin/api.last_used') }}</th>
                            <th>{{ __('admin/api.created') }}</th>
                            <th>{{ __('admin/api.created_by') }}</th>
                            <th></th>
                        </tr>
                        @foreach($keys as $key)
                            <tr>
                                <td><code>
                                    @if (Auth::user()->is($key->user))
                                        {{ $key->identifier . decrypt($key->token) }}
                                    @else
                                        {{ $key->identifier . '****' }}
                                    @endif
                                </code></td>
                                <td>{{ $key->memo }}</td>
                                <td>
                                    @if(!is_null($key->last_used_at))
                                        @datetimeHuman($key->last_used_at)
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>@datetimeHuman($key->created_at)</td>
                                <td>
                                    <a href="{{ route('admin.users.view', $key->user->id) }}">{{ $key->user->username }}</a>
                                </td>
                                <td>
                                    <a href="#" data-action="revoke-key" data-attr="{{ $key->identifier }}">
                                        <i class="fa fa-trash-o text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function() {
            $('[data-action="revoke-key"]').click(function (event) {
                var self = $(this);
                event.preventDefault();
                swal({
                    type: 'error',
                    title: @json(__('admin/api.revoke_title')),
                    text: @json(__('admin/api.revoke_text')),
                    cancelButtonText: @json(__('admin/common.actions.cancel')),
                    showCancelButton: true,
                    allowOutsideClick: true,
                    closeOnConfirm: false,
                    confirmButtonText: @json(__('admin/api.revoke')),
                    confirmButtonColor: '#d9534f',
                    showLoaderOnConfirm: true
                }, function () {
                    $.ajax({
                        method: 'DELETE',
                        url: '/admin/api/revoke/' + self.data('attr'),
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).done(function () {
                        swal({
                            type: 'success',
                            title: '',
                            text: @json(__('admin/api.revoked'))
                        });
                        self.parent().parent().slideUp();
                    }).fail(function (jqXHR) {
                        console.error(jqXHR);
                        swal({
                            type: 'error',
                            title: @json(__('admin/common.whoops')),
                            text: @json(__('admin/api.revoke_error'))
                        });
                    });
                });
            });
        });
    </script>
@endsection
