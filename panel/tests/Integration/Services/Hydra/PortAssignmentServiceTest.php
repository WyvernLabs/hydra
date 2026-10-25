<?php

namespace Pterodactyl\Tests\Integration\Services\Hydra;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Network\EggPorts;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Hydra\Network\PortAssignmentService;

class PortAssignmentServiceTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.ports.enabled', true);
    }

    public function testParsesAndFormatsTheAdminLines(): void
    {
        $ports = EggPorts::parse("+1|Raw Port\r\nquery_port|Query|connect\nRCON_PORT||connect\n\ninvalid line!|x\nRAW+2");

        $this->assertSame([
            ['variable' => null, 'offset' => 1, 'label' => 'Raw Port', 'connect' => false],
            ['variable' => 'QUERY_PORT', 'offset' => null, 'label' => 'Query', 'connect' => true],
            // Only the first port can be the one to connect over.
            ['variable' => 'RCON_PORT', 'offset' => null, 'label' => null, 'connect' => false],
            ['variable' => 'RAW', 'offset' => 2, 'label' => null, 'connect' => false],
        ], $ports);
        $this->assertNull(EggPorts::parse(''));

        $egg = new Egg(['hydra_ports' => $ports]);
        $this->assertSame("+1|Raw Port\nQUERY_PORT|Query|connect\nRCON_PORT\nRAW+2", str_replace(PHP_EOL, "\n", EggPorts::format($egg)));
    }

    public function testConnectAddressIsTheMarkedPort(): void
    {
        [$server, $egg] = $this->server();
        $this->allocation($server, $server->allocation->port + 1);
        $query = $this->allocation($server, $server->allocation->port + 5);
        $this->service()->assign($server);

        $this->assertNull(EggPorts::connectAllocation($server->refresh()));

        $ports = $egg->hydra_ports;
        $ports[1]['connect'] = true;
        $egg->forceFill(['hydra_ports' => $ports])->save();

        $this->assertSame($query->id, EggPorts::connectAllocation($server->refresh())?->id);
    }

    public function testAssignsPortsAndVariables(): void
    {
        [$server, $egg] = $this->server();
        $raw = $this->allocation($server, $server->allocation->port + 1);
        $query = $this->allocation($server, $server->allocation->port + 5);
        $other = Allocation::factory()->create(['node_id' => $server->node_id]);

        $this->service()->assign($server);

        $this->assertSame($server->id, $raw->refresh()->server_id);
        $this->assertSame('Raw Port', $raw->notes);
        $this->assertSame($server->id, $query->refresh()->server_id);
        $this->assertSame('Query Port', $query->notes);
        $this->assertNull($other->refresh()->server_id);
        $this->assertSame((string) $query->port, $this->value($server, $egg, 'QUERY_PORT'));

        // Running it again keeps everything as it is.
        $this->service()->assign($server->refresh());
        $this->assertSame(3, Allocation::query()->where('server_id', $server->id)->count());
        $this->assertSame((string) $query->port, $this->value($server, $egg, 'QUERY_PORT'));
    }

    public function testFailsWithoutRoomForTheOffsetPort(): void
    {
        [$server] = $this->server();
        $this->allocation($server, $server->allocation->port + 5);

        $this->expectException(DisplayException::class);
        $this->service()->assign($server);
    }

    public function testMovesThePrimaryPortWhenAllowed(): void
    {
        [$server] = $this->server();
        $old = $server->allocation;
        $primary = $this->allocation($server, $old->port + 10);
        $raw = $this->allocation($server, $old->port + 11);
        $this->allocation($server, $old->port + 20);

        $this->service()->assign($server, true);

        $server->refresh();
        $this->assertSame($primary->id, $server->allocation_id);
        $this->assertNull($old->refresh()->server_id);
        $this->assertSame($server->id, $raw->refresh()->server_id);
    }

    public function testDoesNothingWhileTheAddonIsOff(): void
    {
        config()->set('hydra.addons.ports.enabled', false);
        [$server] = $this->server();
        $raw = $this->allocation($server, $server->allocation->port + 1);

        $this->service()->assign($server);

        $this->assertNull($raw->refresh()->server_id);
    }

    /**
     * A server whose egg needs the primary port + 1 and a query port.
     *
     * @return array{Server, Egg}
     */
    private function server(): array
    {
        $server = $this->createServerModel();
        $egg = $this->cloneEggAndVariables($server->egg);
        EggVariable::query()->create([
            'egg_id' => $egg->id,
            'name' => 'Query Port',
            'description' => '',
            'env_variable' => 'QUERY_PORT',
            'default_value' => '27015',
            'user_viewable' => true,
            'user_editable' => false,
            'rules' => 'required|integer',
        ]);
        $egg->forceFill(['hydra_ports' => [
            ['variable' => null, 'offset' => 1, 'label' => 'Raw Port'],
            ['variable' => 'QUERY_PORT', 'offset' => null, 'label' => 'Query Port'],
        ]])->save();

        // Keep the port away from the end of the range for the "+N" ports of the tests.
        $server->allocation->forceFill(['port' => random_int(2000, 60000)])->save();
        $server->forceFill(['egg_id' => $egg->id])->save();

        return [$server->refresh(), $egg];
    }

    private function allocation(Server $server, int $port): Allocation
    {
        return Allocation::factory()->create(['node_id' => $server->node_id, 'ip' => $server->allocation->ip, 'port' => $port]);
    }

    private function value(Server $server, Egg $egg, string $env): ?string
    {
        $variable = EggVariable::query()->where('egg_id', $egg->id)->where('env_variable', $env)->firstOrFail();

        return ServerVariable::query()->where('server_id', $server->id)->where('variable_id', $variable->id)->value('variable_value');
    }

    private function service(): PortAssignmentService
    {
        return $this->app->make(PortAssignmentService::class);
    }
}
