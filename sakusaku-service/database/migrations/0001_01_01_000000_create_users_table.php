<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('wp_site_url', 2048);
            $table->string('wp_api_key', 64);
            $table->string('wp_api_endpoint', 2048)->nullable();
            $table->enum('docs_retrieval_method', ['url_direct', 'oauth', 'service_account'])->default('url_direct');
            $table->json('gcp_credentials')->nullable();
            $table->enum('status', ['trial', 'active', 'suspended', 'cancelled'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('notification_google_chat_webhook', 2048)->nullable();
            $table->string('notification_teams_webhook', 2048)->nullable();
            $table->json('notification_events')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('google_id');
            $table->string('email');
            $table->string('name');
            $table->string('avatar_url', 2048)->nullable();
            $table->enum('role', ['poster', 'admin'])->default('poster');
            $table->tinyInteger('level')->unsigned()->default(1);
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'google_id']);
            $table->index('email');
            $table->index(['tenant_id', 'role']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
