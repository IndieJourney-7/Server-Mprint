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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('design_id')->nullable()->constrained('user_designs')->onDelete('cascade');
            $table->string('orientation')->default('horizontal');
            $table->string('shape')->default('rectangle');
            $table->json('selected_attributes')->nullable();
            $table->json('front_canvas_state')->nullable();
            $table->json('back_canvas_state')->nullable();
            $table->string('front_thumbnail')->nullable();
            $table->string('back_thumbnail')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'product_id']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
