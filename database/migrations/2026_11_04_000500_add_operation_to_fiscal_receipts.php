<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table): void {
            $table->string('operation', 16)->default('issue')->after('sale_id');
            $table->index(['sale_id', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table): void {
            $table->dropIndex(['sale_id', 'operation']);
            $table->dropColumn('operation');
        });
    }
};
