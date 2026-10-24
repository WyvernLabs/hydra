<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: subdomains on Cloudflare domains (addon "subdomains"), reverse
     * proxies of the nodes (addon "proxy") and servers with a dedicated node
     * whose owner picks the ports.
     */
    public function up(): void
    {
        Schema::create('hydra_domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('zone_id', 64);
            $table->text('api_token');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('hydra_subdomains', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('domain_id');
            $table->string('name', 63);
            $table->unsignedInteger('allocation_id')->nullable();
            // SRV service, e.g. "minecraft" for _minecraft._tcp; null for none.
            $table->string('srv_service', 32)->nullable();
            // Ids of the DNS records at Cloudflare.
            $table->json('records');
            // Created for a reverse proxy, does not count as a subdomain.
            $table->boolean('for_proxy')->default(false);
            $table->timestamps();

            $table->unique(['domain_id', 'name']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('domain_id')->references('id')->on('hydra_domains')->restrictOnDelete();
            $table->foreign('allocation_id')->references('id')->on('allocations')->nullOnDelete();
        });

        Schema::create('hydra_proxies', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('domain')->unique();
            $table->unsignedInteger('allocation_id');
            $table->unsignedInteger('subdomain_id')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('allocation_id')->references('id')->on('allocations')->cascadeOnDelete();
            $table->foreign('subdomain_id')->references('id')->on('hydra_subdomains')->nullOnDelete();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('hydra_dedicated')->default(false);
            // Null uses the default of the addon.
            $table->unsignedInteger('hydra_subdomain_limit')->nullable();
            $table->unsignedInteger('hydra_proxy_limit')->nullable();
        });

        Schema::table('allocations', function (Blueprint $table) {
            // Created by the owner of a server with a dedicated node, removed with it.
            $table->boolean('hydra_dedicated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropColumn('hydra_dedicated');
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['hydra_dedicated', 'hydra_subdomain_limit', 'hydra_proxy_limit']);
        });
        Schema::dropIfExists('hydra_proxies');
        Schema::dropIfExists('hydra_subdomains');
        Schema::dropIfExists('hydra_domains');
    }
};
