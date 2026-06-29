<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rescue_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable();
            $table->string('tid')->nullable();
            $table->string('status')->nullable();
            $table->float('conf')->nullable();
            $table->text('message')->nullable();
            $table->json('bbox_json')->nullable();
            $table->json('dbg_json')->nullable();
            $table->timestamp('event_time')->nullable();
            $table->string('review_status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rescue_events');
    }
};