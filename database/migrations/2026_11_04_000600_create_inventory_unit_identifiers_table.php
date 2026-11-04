<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_unit_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('value', 128);
            $table->timestamps();
            $table->unique(['organization_id', 'value'], 'unit_identifier_org_value_uq');
            $table->unique(['inventory_unit_id', 'kind'], 'unit_identifier_unit_kind_uq');
        });

        DB::table('inventory_units')
            ->select(['id', 'organization_id', 'serial_number', 'imei1', 'imei2'])
            ->orderBy('id')
            ->chunkById(500, function ($units): void {
                $now = now();
                $rows = [];

                foreach ($units as $unit) {
                    foreach (['serial' => $unit->serial_number, 'imei1' => $unit->imei1, 'imei2' => $unit->imei2] as $kind => $value) {
                        if ($value === null || trim((string) $value) === '') {
                            continue;
                        }

                        $rows[] = [
                            'organization_id' => $unit->organization_id,
                            'inventory_unit_id' => $unit->id,
                            'kind' => $kind,
                            'value' => (string) $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('inventory_unit_identifiers')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_identifiers');
    }
};
