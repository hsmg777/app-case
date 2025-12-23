<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->string('ref_type')->nullable()->after('reason');
            $table->unsignedBigInteger('ref_id')->nullable()->after('ref_type');

            // evita duplicados por sesión + referencia
            $table->unique(['cash_session_id', 'ref_type', 'ref_id'], 'uniq_cash_movement_ref');
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropUnique('uniq_cash_movement_ref');
            $table->dropColumn(['ref_type', 'ref_id']);
        });
    }
};
