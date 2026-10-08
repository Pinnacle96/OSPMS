<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->char('public_id', 26)->unique();
            $t->string('ticket_reference', 80)->unique();
            foreach (['revenue_head', 'fee_configuration', 'lga', 'park'] as $relation) {
                $t->foreignId($relation.'_id')->constrained()->restrictOnDelete();
            }
            foreach (['operator', 'driver', 'vehicle', 'route'] as $relation) {
                $t->foreignId($relation.'_id')->nullable()->constrained()->restrictOnDelete();
            }
            $t->string('fee_code_snapshot', 50);
            $t->string('fee_name_snapshot', 190);
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3)->default('NGN');
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('issued_at');
            $t->dateTime('expires_at')->nullable();
            $t->string('ticket_status', 30);
            $t->string('payment_status', 30);
            $t->string('verification_token', 128)->unique();
            $t->json('context_snapshot')->nullable();
            $t->timestamps();
            $t->index('issued_at');
            foreach (['lga', 'park', 'vehicle', 'driver'] as $relation) {
                $t->index([$relation.'_id', 'issued_at']);
            }
            $t->index(['ticket_status', 'payment_status']);
            $t->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
