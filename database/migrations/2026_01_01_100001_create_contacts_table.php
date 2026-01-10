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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Customer Information
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Inquiry Details
            $table->enum('inquiry_type', [
                'order_status',
                'order_issue',
                'product_question',
                'design_help',
                'bulk_quote',
                'returns_refunds',
                'print_quality',
                'shipping',
                'billing',
                'website_issue',
                'partnership',
                'other'
            ])->default('other');

            $table->string('order_number')->nullable();
            $table->string('product_name')->nullable();
            $table->string('subject');
            $table->text('message');

            // File Attachment (for design files or damage photos)
            $table->string('attachment')->nullable();

            // Preferred Contact Method
            $table->enum('preferred_contact', ['email', 'phone', 'any'])->default('email');

            // Status Management
            $table->enum('status', ['new', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Admin Response
            $table->text('admin_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // User reference (if logged in)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Tracking
            $table->string('ip_address')->nullable();
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            // Indexes for faster queries
            $table->index(['status', 'created_at']);
            $table->index(['inquiry_type', 'status']);
            $table->index('order_number');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
