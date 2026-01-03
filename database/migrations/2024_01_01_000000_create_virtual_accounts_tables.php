<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->index();
            $table->string('account_number')->unique()->index();
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('bank_code');
            $table->string('provider_reference')->index();
            $table->string('provider')->index();
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'provider']);
            $table->index(['status', 'provider']);
        });

        Schema::create('incoming_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique()->index();
            $table->string('transaction_reference')->index();
            $table->string('provider_reference')->index();
            $table->string('account_number')->index();
            $table->decimal('amount', 15);
            $table->string('currency', 3)->default('NGN');
            $table->string('sender_name');
            $table->string('sender_account')->nullable();
            $table->string('sender_bank')->nullable();
            $table->text('narration')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->string('provider')->index();
            $table->string('status')->default('confirmed')->index();
            $table->timestamp('settled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_number', 'status']);
            $table->index(['provider', 'status']);
            $table->index(['created_at', 'status']);
        });

        Schema::create('provider_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('event_type');
            $table->json('payload');
            $table->string('transaction_reference')->nullable()->index();
            $table->boolean('processed')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'processed']);
            $table->index(['created_at', 'processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_logs');
        Schema::dropIfExists('incoming_transfers');
        Schema::dropIfExists('virtual_accounts');
    }
};