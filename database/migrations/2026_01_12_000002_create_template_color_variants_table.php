<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_color_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->onDelete('cascade');
            $table->string('color_name'); // e.g., "Blue", "Red", "Black"
            $table->string('color_hex'); // e.g., "#3B82F6"
            $table->string('preview_image'); // Preview with this color
            $table->string('front_template_path')->nullable();
            $table->string('back_template_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_color_variants');
    }
};
