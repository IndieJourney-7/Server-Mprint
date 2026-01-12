<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('subcategory_id')->nullable()->constrained()->onDelete('set null');
            
            // Template files
            $table->string('preview_image'); // Main preview image
            $table->string('front_template_path')->nullable(); // Front design template
            $table->string('back_template_path')->nullable(); // Back design template
            
            // Template metadata
            $table->enum('orientation', ['horizontal', 'vertical'])->default('horizontal');
            $table->enum('corners', ['rectangle', 'rounded'])->default('rectangle');
            $table->json('available_colors')->nullable(); // Array of color variants
            $table->json('customizable_fields')->nullable(); // Fields that can be customized
            
            // Pricing
            $table->decimal('base_price', 10, 2)->default(0);
            
            // Template properties
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->integer('usage_count')->default(0);
            
            // Print dimensions (from product)
            $table->decimal('print_width_inches', 8, 2)->nullable();
            $table->decimal('print_length_inches', 8, 2)->nullable();
            
            $table->timestamps();
            
            $table->index(['category_id', 'is_active']);
            $table->index('orientation');
            $table->index('corners');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
