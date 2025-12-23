<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('client_email_id')->nullable()->after('client_id');
            $table->string('email_destino', 255)->nullable()->after('client_email_id');

            $table->foreign('client_email_id')
                  ->references('id')
                  ->on('client_emails')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['client_email_id']);
            $table->dropColumn(['client_email_id', 'email_destino']);
        });
    }
};
