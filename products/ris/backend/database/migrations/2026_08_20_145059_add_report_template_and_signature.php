<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->text('report_template')->nullable()->after('modality'); // isi default findings/impression
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->string('signed_by')->nullable()->after('status');
            $table->timestamp('signed_at')->nullable()->after('signed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn('report_template');
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['signed_by', 'signed_at']);
        });
    }
};
