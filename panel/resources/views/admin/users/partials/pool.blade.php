{{-- Hydra: resource pool of a user (addon "resource_pool"). Expects $user and $canManage. --}}
@php
    $poolService = app(\Pterodactyl\Services\Hydra\Pool\ResourcePoolService::class);
    $pool = $user->resourcePool()->first();
    $ownServers = \Pterodactyl\Models\Server::query()->where('owner_id', $user->id)->withCount('allocations')->orderBy('name')->get();
    $poolServers = $ownServers->where('hydra_pool', true);
    $used = $poolService->used($user, $poolServers);
    $limits = $pool?->limits() ?? array_fill_keys(array_merge(['servers'], \Pterodactyl\Models\HydraResourcePool::RESOURCES), 0);
    // Cores and GB in the form, percent and MiB in the database.
    $shown = fn (string $key, int $value) => match ($key) {
        'cpu' => round($value / 100, 2),
        'memory', 'disk', 'backups' => round($value / 1024, 2),
        default => $value,
    };
    $units = ['cpu' => __('admin/users.pool.unit_cores'), 'memory' => 'GB', 'disk' => 'GB', 'backups' => 'GB'];
    $mayEdit = $canManage && Auth::user()->hasAdminPermission('users.pool');
@endphp
<div class="col-xs-12">
    <form action="{{ route('admin.users.pool', $user->id) }}" method="POST">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="box {{ $pool ? 'box-primary' : '' }}" id="resourcePool">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/users.pool.title') }}</h3>
            </div>
            <fieldset class="box-body" @disabled(!$mayEdit)>
                <p class="text-muted small">{{ __('admin/users.pool.help') }}</p>
                <div class="checkbox checkbox-primary">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" id="poolActive" name="active" value="1" @checked(old('active', (bool) $pool))>
                    <label for="poolActive">{{ __('admin/users.pool.active') }}</label>
                </div>
                <div class="row">
                    @foreach(array_merge(['servers'], \Pterodactyl\Models\HydraResourcePool::RESOURCES) as $key)
                        <div class="form-group col-sm-6 col-md-4 col-lg-3">
                            <label for="pool_{{ $key }}" class="control-label">{{ __("admin/users.pool.resources.$key") }}</label>
                            <div class="input-group">
                                <input type="number" id="pool_{{ $key }}" name="{{ $key }}" class="form-control" min="0"
                                       step="{{ in_array($key, ['cpu', 'memory', 'disk', 'backups'], true) ? '0.01' : '1' }}"
                                       value="{{ old($key, is_null($limits[$key]) ? '' : $shown($key, $limits[$key])) }}"
                                       @if($key === 'servers') placeholder="∞" @endif>
                                <span class="input-group-addon">{{ $units[$key] ?? __('admin/users.pool.unit_count') }}</span>
                            </div>
                            <p class="text-muted small no-margin" style="margin-top: 4px;">
                                {{ __('admin/users.pool.used', ['used' => $shown($key, $used[$key]), 'limit' => is_null($limits[$key]) ? '∞' : $shown($key, $limits[$key])]) }}
                                @if($key === 'servers')
                                    &middot; {{ __('admin/users.pool.servers_unlimited') }}
                                @endif
                                @if($pool && !is_null($limits[$key]) && $used[$key] > $limits[$key])
                                    <span class="text-danger">&middot; {{ __('admin/users.pool.over') }}</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
                <label class="control-label">{{ __('admin/users.pool.servers_title') }}</label>
                <p class="text-muted small">{{ __('admin/users.pool.servers_help') }}</p>
                @if($ownServers->isEmpty())
                    <p class="text-muted small no-margin">{{ __('admin/users.pool.no_servers') }}</p>
                @else
                    <table class="table table-condensed no-margin">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>{{ __('admin/users.pool.server') }}</th>
                                <th>{{ __('admin/users.pool.resources.cpu') }}</th>
                                <th>{{ __('admin/users.pool.resources.memory') }}</th>
                                <th>{{ __('admin/users.pool.resources.disk') }}</th>
                                <th class="hidden-xs">{{ __('admin/users.pool.resources.databases') }}</th>
                                <th class="hidden-xs">{{ __('admin/users.pool.resources.ports') }}</th>
                                <th class="hidden-xs">{{ __('admin/users.pool.resources.backups') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ownServers as $server)
                                @php($usage = $poolService->usage($server))
                                <tr>
                                    <td>
                                        <div class="checkbox checkbox-primary no-margin">
                                            <input type="checkbox" id="poolServer{{ $server->id }}" name="pool_servers[]" value="{{ $server->id }}" @checked($server->hydra_pool)>
                                            <label for="poolServer{{ $server->id }}"></label>
                                        </div>
                                    </td>
                                    <td><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></td>
                                    <td>{{ $usage['cpu'] > 0 ? $shown('cpu', $usage['cpu']) : '∞' }}</td>
                                    <td>{{ $usage['memory'] > 0 ? $shown('memory', $usage['memory']) . ' GB' : '∞' }}</td>
                                    <td>{{ $usage['disk'] > 0 ? $shown('disk', $usage['disk']) . ' GB' : '∞' }}</td>
                                    <td class="hidden-xs">{{ $usage['databases'] }}</td>
                                    <td class="hidden-xs">{{ $usage['ports'] }}</td>
                                    <td class="hidden-xs">{{ $shown('backups', $usage['backups']) }} GB</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </fieldset>
            @if($mayEdit)
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/users.pool.save') }}</button>
                </div>
            @endif
        </div>
    </form>
</div>
