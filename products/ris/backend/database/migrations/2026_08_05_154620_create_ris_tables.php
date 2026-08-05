<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MS3: RIS — pasien, order, worklist (MWL source).
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id')->unique();      // MRN
            $table->string('name');
            $table->string('sex', 1)->nullable();        // M/F/O
            $table->date('birthdate')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('modality', 8);               // DX/CT/MR/US...
            $table->string('status', 16)->default('scheduled'); // scheduled/in_progress/completed/cancelled
            $table->dateTime('requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('worklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('scheduled_aet', 16)->default('RIS');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 16)->default('pending'); // pending/arrived/started/completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worklist_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('patients');
    }
};
