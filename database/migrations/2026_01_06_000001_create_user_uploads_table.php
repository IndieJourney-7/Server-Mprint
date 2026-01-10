<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User Uploads - Stores all images uploaded by users
     * These can be reused across multiple designs (like Canva's Uploads panel)
     */
    public function up(): void
    {
        Schema::create('user_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // File info
            $table->string('original_name');  // Original filename
            $table->string('file_path');      // Storage path
            $table->string('thumbnail_path')->nullable(); // Thumbnail for preview
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // In bytes
            
            // Image dimensions
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            
            // Usage tracking
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            
            // Organization
            $table->string('folder')->nullable(); // For future folder organization
            $table->boolean('is_favorite')->default(false);
            
            $table->timestamps();
            
            // Indexes for fast queries
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'last_used_at']);
            $table->index(['user_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_uploads');
    }
};
