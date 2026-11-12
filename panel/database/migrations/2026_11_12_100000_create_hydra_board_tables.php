<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Hydra: Kanban board of a server (addon "board"). Columns with cards, tags
 * of the server that are assigned to cards, and a checklist per card. Cards
 * can be claimed by a user to show who works on them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hydra_board_columns', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('name', 64);
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->index(['server_id', 'sort']);
        });

        Schema::create('hydra_board_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('name', 32);
            $table->string('color', 7);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::create('hydra_board_cards', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('column_id');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('column_id')->references('id')->on('hydra_board_columns')->cascadeOnDelete();
            $table->foreign('claimed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['column_id', 'sort']);
        });

        Schema::create('hydra_board_card_tag', function (Blueprint $table) {
            $table->unsignedInteger('card_id');
            $table->unsignedInteger('tag_id');

            $table->primary(['card_id', 'tag_id']);
            $table->foreign('card_id')->references('id')->on('hydra_board_cards')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('hydra_board_tags')->cascadeOnDelete();
        });

        Schema::create('hydra_board_checklist_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('card_id');
            $table->string('text', 255);
            $table->boolean('done')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign('card_id')->references('id')->on('hydra_board_cards')->cascadeOnDelete();
            $table->index(['card_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydra_board_checklist_items');
        Schema::dropIfExists('hydra_board_card_tag');
        Schema::dropIfExists('hydra_board_cards');
        Schema::dropIfExists('hydra_board_tags');
        Schema::dropIfExists('hydra_board_columns');
    }
};
