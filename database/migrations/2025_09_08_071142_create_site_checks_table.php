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
        Schema::create('site_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_link_id');
            $table->foreign('site_link_id')->references('id')->on('site_links')->onDelete('cascade');
            $table->enum('status',['up','down']);
            $table->integer('response_time_ms')->nullable();
            $table->integer('ssl_days_left')->nullable();
            $table->integer('html_bytes')->nullable();
            $table->timestamp('checked_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_checks');
    }
};
