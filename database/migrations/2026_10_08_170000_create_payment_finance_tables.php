<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('payment_reference', 80)->unique();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->string('provider', 50);
            $t->string('provider_reference', 190)->nullable()->index();
            $t->string('channel', 50);
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3)->default('NGN');
            $t->string('status', 30);
            $t->string('idempotency_key', 190)->nullable()->unique();
            $t->dateTime('initiated_at');
            $t->dateTime('paid_at')->nullable();
            $t->dateTime('failed_at')->nullable();
            $t->dateTime('reversed_at')->nullable();
            $t->json('provider_metadata')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['status', 'paid_at']);
            $t->index(['provider', 'provider_reference']);
        });
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('receipt_number', 80)->unique();
            $t->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->string('verification_token', 128)->unique();
            $t->dateTime('issued_at');
            $t->string('rendered_file_path', 500)->nullable();
            $t->dateTime('created_at');
        });
        Schema::create('financial_transactions', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('transaction_reference', 80)->unique();
            $t->foreignId('ticket_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('parent_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $t->string('transaction_type', 30);
            $t->string('direction', 10);
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3)->default('NGN');
            $t->dateTime('occurred_at');
            $t->string('description', 255)->nullable();
            $t->string('source', 50);
            $t->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->json('metadata')->nullable();
            $t->dateTime('created_at');
            $t->index('occurred_at');
            $t->index(['transaction_type', 'occurred_at']);
        });
        Schema::create('financial_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->char('event_id', 26)->unique();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('event_type', 80);
            $t->string('entity_type', 190);
            $t->unsignedBigInteger('entity_id');
            $t->string('business_reference', 190)->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->char('currency', 3)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->json('payload')->nullable();
            $t->string('previous_hash', 128)->nullable();
            $t->string('entry_hash', 128);
            $t->dateTime('occurred_at');
        });
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->string('idempotency_key', 190)->unique();
            $t->string('operation', 100);
            $t->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('request_hash', 128);
            $t->integer('response_status')->nullable();
            $t->json('response_payload')->nullable();
            $t->dateTime('expires_at');
            $t->dateTime('created_at');
        });
    }

    public function down(): void
    {
        foreach (['idempotency_keys', 'financial_audit_logs', 'financial_transactions', 'receipts', 'payments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
