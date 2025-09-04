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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->enum('duration', ['monthly', 'yearly'])->default('monthly');
            $table->boolean('is_active')->default(false);
            // here we set features
            $table->boolean('whatsapp_notifications')->default(false);
            $table->boolean('email_notifications')->default(false);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('push_notifications')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
