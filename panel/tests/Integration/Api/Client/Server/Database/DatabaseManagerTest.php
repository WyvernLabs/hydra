<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Database;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Permission;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\DatabaseHost;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Contracts\Extensions\HashidsInterface;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: database manager, import and export. These tests run against the
 * test database itself, through a database host that points at it.
 */
class DatabaseManagerTest extends ClientApiIntegrationTestCase
{
    private string $table;

    public function setUp(): void
    {
        parent::setUp();

        $this->table = 'hydra_manager_' . substr(md5(uniqid()), 0, 8);
        DB::statement("CREATE TABLE `$this->table` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL, note TEXT NULL)");
        DB::table($this->table)->insert([['name' => 'alpha', 'note' => null], ['name' => 'beta', 'note' => str_repeat('x', 600)]]);
    }

    public function tearDown(): void
    {
        DB::statement("DROP TABLE IF EXISTS `$this->table`");
        DB::statement("DROP TABLE IF EXISTS `{$this->table}_imported`");

        parent::tearDown();
    }

    /**
     * A server database whose host and credentials are the ones of the test
     * connection.
     */
    private function database(Server $server): Database
    {
        $config = config('database.connections.' . config('database.default'));
        $host = DatabaseHost::factory()->create([
            'host' => $config['host'],
            'port' => $config['port'],
            'username' => $config['username'],
            'password' => encrypt($config['password']),
        ]);

        return Database::factory()->create([
            'server_id' => $server->id,
            'database_host_id' => $host->id,
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => encrypt($config['password']),
        ]);
    }

    private function url(Server $server, Database $database, string $path): string
    {
        return $this->link($server, '/databases/' . $this->app->make(HashidsInterface::class)->encode($database->id) . $path);
    }

    public function testTablesAndRowsAreShown(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $database = $this->database($server);

        $this->actingAs($user)->getJson($this->url($server, $database, '/tables'))
            ->assertOk()
            ->assertJsonPath('phpmyadmin', false)
            ->assertJsonFragment(['name' => $this->table, 'view' => false]);

        $response = $this->actingAs($user)->getJson($this->url($server, $database, '/rows') . '?table=' . $this->table . '&sort=name&direction=desc')
            ->assertOk()
            ->assertJsonPath('primary', ['id'])
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('rows.0.values.name', 'beta')
            ->assertJsonPath('rows.0.truncated', ['note'])
            ->assertJsonPath('rows.1.values.note', null);
        $this->assertSame(500, mb_strlen($response->json('rows.0.values.note')));

        // The full value is loaded for editing.
        $this->actingAs($user)->postJson($this->url($server, $database, '/rows/show'), ['table' => $this->table, 'key' => ['id' => 2]])
            ->assertOk()
            ->assertJsonPath('values.note', str_repeat('x', 600));

        $this->actingAs($user)->getJson($this->url($server, $database, '/structure') . '?table=' . $this->table)
            ->assertOk()
            ->assertJsonPath('columns.0.name', 'id')
            ->assertJsonPath('columns.0.primary', true)
            ->assertJsonPath('indexes.0.name', 'PRIMARY');

        // Only tables of the database can be opened.
        $this->actingAs($user)->getJson($this->url($server, $database, '/rows') . '?table=' . urlencode('users` WHERE 1; --'))
            ->assertStatus(422);
    }

    public function testRowsCanBeChanged(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $database = $this->database($server);

        $this->actingAs($user)->postJson($this->url($server, $database, '/rows'), ['table' => $this->table, 'values' => ['name' => 'gamma', 'note' => null]])
            ->assertNoContent();
        $this->assertSame(1, DB::table($this->table)->where('name', 'gamma')->whereNull('note')->count());

        $this->actingAs($user)->patchJson($this->url($server, $database, '/rows'), ['table' => $this->table, 'key' => ['id' => 1], 'values' => ['note' => "it's fine"]])
            ->assertNoContent();
        $this->assertSame("it's fine", DB::table($this->table)->where('id', 1)->value('note'));

        $this->actingAs($user)->deleteJson($this->url($server, $database, '/rows'), ['table' => $this->table, 'key' => ['id' => 1]])
            ->assertNoContent();
        $this->assertSame(0, DB::table($this->table)->where('id', 1)->count());

        // Unknown columns are rejected instead of being put into the statement.
        $this->actingAs($user)->postJson($this->url($server, $database, '/rows'), ['table' => $this->table, 'values' => ['name`) VALUES (1); --' => 'x']])
            ->assertStatus(422);

        $this->actingAs($user)->deleteJson($this->url($server, $database, '/rows'), ['table' => $this->table, 'key' => ['id' => 999]])
            ->assertStatus(422);
    }

    public function testSqlConsole(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $database = $this->database($server);

        $this->actingAs($user)->postJson($this->url($server, $database, '/query'), ['sql' => "SELECT name FROM `$this->table` ORDER BY id"])
            ->assertOk()
            ->assertJsonPath('columns.0.name', 'name')
            ->assertJsonPath('rows', [['alpha'], ['beta']])
            ->assertJsonPath('affected', null);

        $this->actingAs($user)->postJson($this->url($server, $database, '/query'), ['sql' => "UPDATE `$this->table` SET name = 'x'"])
            ->assertOk()
            ->assertJsonPath('affected', 2);

        $this->actingAs($user)->postJson($this->url($server, $database, '/query'), ['sql' => 'SELEC nonsense'])
            ->assertStatus(422);
    }

    public function testImportAndExport(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $database = $this->database($server);

        $sql = "CREATE TABLE `{$this->table}_imported` (id INT PRIMARY KEY);\nINSERT INTO `{$this->table}_imported` VALUES (1), (2);\n";
        $file = UploadedFile::fake()->createWithContent('dump.sql.gz', gzencode($sql));

        $this->actingAs($user)->post($this->url($server, $database, '/import'), ['file' => $file], ['Accept' => 'application/json'])
            ->assertNoContent();
        $this->assertSame(2, DB::table($this->table . '_imported')->count());

        $broken = UploadedFile::fake()->createWithContent('broken.sql', 'THIS IS NOT SQL;');
        $this->actingAs($user)->post($this->url($server, $database, '/import'), ['file' => $broken], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $url = $this->actingAs($user)->postJson($this->url($server, $database, '/export'))->assertOk()->json('url');

        $response = $this->actingAs($user, 'web')->get($url);
        $response->assertOk()->assertHeader('Content-Type', 'application/gzip');
        $this->assertStringContainsString("CREATE TABLE `$this->table`", gzdecode($response->streamedContent()));

        // The link works only once.
        $this->actingAs($user, 'web')->get($url)->assertNotFound();
    }

    public function testPermissionsAreChecked(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_DATABASE_READ, Permission::ACTION_DATABASE_BROWSE]);
        $database = $this->database($server);

        $this->actingAs($user)->getJson($this->url($server, $database, '/tables'))->assertOk();
        $this->actingAs($user)->postJson($this->url($server, $database, '/query'), ['sql' => 'SELECT 1'])->assertForbidden();
        $this->actingAs($user)->postJson($this->url($server, $database, '/rows'), ['table' => $this->table, 'values' => []])->assertForbidden();
        $this->actingAs($user)->postJson($this->url($server, $database, '/export'))->assertForbidden();
        $this->actingAs($user)->post($this->url($server, $database, '/import'), [], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($user)->postJson($this->url($server, $database, '/phpmyadmin'))->assertForbidden();

        // A database of another server is not found.
        $other = $this->database($this->createServerModel());
        $this->actingAs($user)->getJson($this->url($server, $other, '/tables'))->assertNotFound();
    }

    public function testPhpMyAdminNeedsTheAddon(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $database = $this->database($server);

        $this->actingAs($user)->postJson($this->url($server, $database, '/phpmyadmin'))->assertStatus(409);
    }
}
