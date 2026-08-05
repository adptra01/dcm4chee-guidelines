<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MS5: Reporting — laporan radiologi per order.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('radiologist', 100)->nullable();
            $table->text('findings')->nullable();     // temuan
            $table->text('impression')->nullable();   // kesan
            $table->string('status', 16)->default('draft'); // draft/final/amended
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
