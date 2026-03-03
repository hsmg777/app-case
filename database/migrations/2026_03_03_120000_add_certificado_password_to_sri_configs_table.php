<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sri_configs', 'certificado_password')) {
            Schema::table('sri_configs', function (Blueprint $table) {
                $table->string('certificado_password', 255)->nullable()->after('ruta_certificado');
            });
        }

        if (Schema::hasColumn('sri_configs', 'clave_certificado')) {
            DB::statement("
                UPDATE sri_configs
                SET certificado_password = COALESCE(certificado_password, clave_certificado)
                WHERE certificado_password IS NULL
                  AND clave_certificado IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sri_configs', 'certificado_password')) {
            Schema::table('sri_configs', function (Blueprint $table) {
                $table->dropColumn('certificado_password');
            });
        }
    }
};
