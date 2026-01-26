<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop existing check constraint in Postgres
        DB::statement('ALTER TABLE electronic_invoices DROP CONSTRAINT IF EXISTS electronic_invoices_estado_sri_check');

        // 2. Change column to allow all necessary states
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->string('estado_sri', 30)
                ->default('PENDIENTE_ENVIO')
                ->change();
        });

        // 3. Add new check constraint with additional states
        // Including: EN_PROCESO, PENDIENTE_REINTENTO, EN_PROCESAMIENTO, PENDIENTE
        DB::statement("ALTER TABLE electronic_invoices ADD CONSTRAINT electronic_invoices_estado_sri_check CHECK (estado_sri IN (
            'PENDIENTE_ENVIO', 
            'ENVIADO', 
            'AUTORIZADO', 
            'RECHAZADO', 
            'EN_PROCESO', 
            'PENDIENTE_REINTENTO', 
            'EN_PROCESAMIENTO', 
            'PENDIENTE'
        ))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE electronic_invoices DROP CONSTRAINT IF EXISTS electronic_invoices_estado_sri_check');

        Schema::table('electronic_invoices', function (Blueprint $table) {
            // Restore original enum-like string if possible, 
            // but we'll stick to a simpler string to avoid rollback failures if new values exist.
            $table->string('estado_sri', 30)->change();
        });

        DB::statement("ALTER TABLE electronic_invoices ADD CONSTRAINT electronic_invoices_estado_sri_check CHECK (estado_sri IN (
            'PENDIENTE_ENVIO', 
            'ENVIADO', 
            'AUTORIZADO', 
            'RECHAZADO'
        ))");
    }
};
