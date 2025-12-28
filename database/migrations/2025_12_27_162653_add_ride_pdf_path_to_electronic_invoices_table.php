<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->string('ride_pdf_path')->nullable()->after('xml_autorizado_path');
        });
    }

    public function down(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->dropColumn('ride_pdf_path');
        });
    }
};
