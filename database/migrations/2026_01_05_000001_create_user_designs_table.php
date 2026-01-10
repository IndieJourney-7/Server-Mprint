<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');

            // Design name for "My Projects" feature
            $table->string('name')->nullable();

            // Front design files
            $table->string('front_original_path')->nullable(); // High-res for printing
            $table->string('front_thumbnail_path')->nullable(); // For preview
            $table->json('front_position_data')->nullable(); // {x, y, width, height, rotation, naturalWidth, naturalHeight}

            // Back design files
            $table->string('back_original_path')->nullable();
            $table->string('back_thumbnail_path')->nullable();
            $table->json('back_position_data')->nullable();

            // Card settings
            $table->enum('orientation', ['horizontal', 'vertical'])->default('horizontal');

            // Status: draft, completed, ordered
            $table->enum('status', ['draft', 'completed', 'ordered'])->default('draft');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('product_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_designs');
    }
};
