<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_chat_threads')) {
            Schema::create('support_chat_threads', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('visitor_name', 120)->nullable();
                $table->string('visitor_email', 190)->nullable();
                $table->string('visitor_token', 80)->nullable()->unique();
                $table->string('status', 20)->default('open')->index();
                $table->unsignedBigInteger('assigned_admin_id')->nullable()->index();
                $table->string('last_sender_type', 20)->default('guest')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->string('started_page_url', 2048)->nullable();
                $table->string('started_page_title', 255)->nullable();
                $table->longText('session_meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_chat_messages')) {
            Schema::create('support_chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('thread_id')->index();
                $table->string('sender_type', 20)->index();
                $table->unsignedBigInteger('sender_user_id')->nullable()->index();
                $table->unsignedBigInteger('sender_admin_id')->nullable()->index();
                $table->text('body');
                $table->timestamp('seen_at')->nullable()->index();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_chat_messages')) {
            Schema::drop('support_chat_messages');
        }

        if (Schema::hasTable('support_chat_threads')) {
            Schema::drop('support_chat_threads');
        }
    }
};
