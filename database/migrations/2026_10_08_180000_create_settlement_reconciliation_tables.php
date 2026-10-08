<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('settlement_reference', 80)->unique();
            $t->string('provider', 50);
            $t->string('provider_settlement_reference', 190)->nullable();
            $t->dateTime('period_start');
            $t->dateTime('period_end');
            $t->decimal('gross_amount', 15, 2);
            $t->decimal('provider_fees', 15, 2)->default(0);
            $t->decimal('net_amount', 15, 2);
            $t->char('currency', 3)->default('NGN');
            $t->string('government_account_reference', 190)->nullable();
            $t->string('status', 30);
            $t->dateTime('settled_at')->nullable();
            $t->json('metadata')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });
        Schema::create('settlement_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('settlement_id')->constrained()->restrictOnDelete();
            $t->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 15, 2);
            $t->string('status', 30);
            $t->dateTime('created_at');
            $t->unique(['settlement_id', 'financial_transaction_id']);
        });
        Schema::create('reconciliation_runs', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('reconciliation_reference', 80)->unique();
            $t->dateTime('period_start');
            $t->dateTime('period_end');
            $t->foreignId('lga_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('park_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('provider', 50)->nullable();
            $t->string('status', 30);
            $t->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('started_at');
            $t->dateTime('completed_at')->nullable();
            $t->json('summary')->nullable();
            $t->dateTime('created_at');
            $t->index(['status', 'period_start', 'period_end']);
        });
        Schema::create('reconciliation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reconciliation_run_id')->constrained()->restrictOnDelete();
            $t->foreignId('ticket_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('financial_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('settlement_item_id')->nullable()->constrained()->restrictOnDelete();
            foreach (['expected_amount', 'actual_amount', 'difference_amount'] as $column) {
                $t->decimal($column, 15, 2);
            }
            $t->string('status', 30)->index();
            $t->string('exception_type', 80)->nullable();
            $t->text('resolution_note')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('resolved_at')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        foreach (['reconciliation_items', 'reconciliation_runs', 'settlement_items', 'settlements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
