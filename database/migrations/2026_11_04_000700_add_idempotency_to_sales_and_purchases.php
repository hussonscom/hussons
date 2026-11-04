<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable();
            $table->char('idempotency_hash', 64)->nullable();
            $table->unique(
                ['organization_id', 'idempotency_key'],
                'sales_org_idempotency_uq',
            );
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable();
            $table->char('idempotency_hash', 64)->nullable();
            $table->unique(
                ['organization_id', 'idempotency_key'],
                'purchases_org_idempotency_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique('sales_org_idempotency_uq');
            $table->dropColumn(['idempotency_key', 'idempotency_hash']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropUnique('purchases_org_idempotency_uq');
            $table->dropColumn(['idempotency_key', 'idempotency_hash']);
        });
    }
};
