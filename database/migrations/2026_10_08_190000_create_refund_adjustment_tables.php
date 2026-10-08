<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('refund_reference', 80)->unique();
            $t->foreignId('payment_id')->constrained()->restrictOnDelete();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3);
            $t->text('reason');
            $t->string('status', 30);
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('provider_reference', 190)->nullable();
            $t->dateTime('requested_at');
            $t->dateTime('processed_at')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->index(['payment_id', 'status']);
        });
        Schema::create('financial_adjustments', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('adjustment_reference', 80)->unique();
            $t->foreignId('original_transaction_id')->constrained('financial_transactions')->restrictOnDelete();
            $t->string('adjustment_type', 30);
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3);
            $t->text('reason');
            $t->string('status', 30);
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('requested_at');
            $t->dateTime('approved_at')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->index(['original_transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('refunds');
    }
};
