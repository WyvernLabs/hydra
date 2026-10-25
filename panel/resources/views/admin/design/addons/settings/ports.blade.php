{{-- Hydra: settings of the automatic ports addon. Expects $addon. --}}
@php
    $eggs = $addon->eggs();
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.ports.eggs') }}</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                @if($eggs->isEmpty())
                    <p class="text-muted" style="padding: 15px; margin: 0;">{{ __('admin/addons.ports.no_eggs') }}</p>
                @else
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>{{ __('admin/addons.ports.egg') }}</th>
                                <th>{{ __('admin/addons.ports.ports') }}</th>
                            </tr>
                            @foreach($eggs as $egg)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.nests.egg.view', $egg->id) }}">{{ $egg->name }}</a>
                                        <span class="text-muted small">({{ $egg->nest->name }})</span>
                                    </td>
                                    <td>
                                        @foreach(\Pterodactyl\Services\Hydra\Network\EggPorts::of($egg) as $port)
                                            <div>
                                                <code>{{ $port['variable'] ?? __('admin/addons.ports.no_variable') }}</code>
                                                @unless(is_null($port['offset']))
                                                    <span class="text-muted small">{{ __('admin/addons.ports.offset', ['offset' => $port['offset']]) }}</span>
                                                @endunless
                                                @unless(is_null($port['label']))
                                                    – {{ $port['label'] }}
                                                @endunless
                                                @if($port['connect'])
                                                    <span class="label label-success">{{ __('admin/addons.ports.connect') }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.ports.notice') }}</p>
            </div>
        </div>
    </div>
</div>
