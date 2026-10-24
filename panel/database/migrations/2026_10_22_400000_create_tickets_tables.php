<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: support tickets (tickets addon) with their messages and the
     * images uploaded for them.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            // Optional: the server the ticket is about.
            $table->unsignedInteger('server_id')->nullable();
            $table->string('category', 64)->nullable();
            $table->string('subject', 191);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_reply_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->mediumText('body');
            // Written by the team (admin area).
            $table->boolean('staff')->default(false);
            // Notes of the team that the user never sees.
            $table->boolean('internal')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            // Both null until the message showing the image is sent.
            $table->unsignedInteger('ticket_id')->nullable();
            $table->unsignedInteger('message_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 191);
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('ticket_messages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
