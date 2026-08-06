<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R6: StudyInstanceUID pada order → jembatan ke OHIF viewer.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('study_instance_uid', 64)->nullable()->after('modality');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('study_instance_uid');
        });
    }
};
