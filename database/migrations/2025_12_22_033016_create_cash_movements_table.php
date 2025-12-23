<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_session_id')
                ->constrained('cash_sessions')
                ->cascadeOnDelete();

            $table->string('type', 3); // IN / OUT
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            $table->index(['cash_session_id', 'type']);
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
