<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['provider_transaction_id', 'type'], 'transactions_provider_type_unique');
            $table->unique(['original_transaction_id', 'type'], 'transactions_original_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_provider_type_unique');
            $table->dropUnique('transactions_original_type_unique');
        });
    }
};
