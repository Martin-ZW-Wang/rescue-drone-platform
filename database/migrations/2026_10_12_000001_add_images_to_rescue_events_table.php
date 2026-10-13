<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rescue_events', function (Blueprint $table) {
            $table->string('original_image_path')->nullable()->after('message');
            $table->string('annotated_image_path')->nullable()->after('original_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('rescue_events', function (Blueprint $table) {
            $table->dropColumn(['original_image_path', 'annotated_image_path']);
        });
    }
};
