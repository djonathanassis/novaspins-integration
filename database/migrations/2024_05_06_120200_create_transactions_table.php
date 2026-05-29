<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('provider_transaction_id')->index();
            $table->string('type', 16);
            $table->double('amount');
            $table->string('status', 16)->default('completed');
            $table->unsignedBigInteger('original_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('original_transaction_id')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
