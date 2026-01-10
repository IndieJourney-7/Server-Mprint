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
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();

            // FAQ Content
            $table->string('question');
            $table->text('answer');

            // Categorization
            $table->string('category')->default('general');

            // Display Control
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Analytics
            $table->integer('views')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index('category');
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
