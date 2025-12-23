<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('caja_id');

            $table->unsignedBigInteger('opened_by');
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 12, 2);

            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->json('closing_count')->nullable();

            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('declared_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();

            $table->string('status', 10)->default('OPEN'); // OPEN / CLOSED
            $table->string('result', 10)->nullable();      // MATCH / SHORT / OVER
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['caja_id', 'status']);
            $table->foreign('opened_by')->references('id')->on('users');
            $table->foreign('closed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
