<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add canvas state for editing and final images for ordering
     */
    public function up(): void
    {
        Schema::table('user_designs', function (Blueprint $table) {
            // Canvas state JSON for front and back (stores all elements, positions, etc.)
            // This allows users to come back and edit their design exactly as they left it
            $table->json('front_canvas_state')->nullable()->after('front_position_data');
            $table->json('back_canvas_state')->nullable()->after('back_position_data');

            // Final rendered images for printing (high-res flattened PNGs)
            // These are generated when user places an order
            $table->string('front_final_path')->nullable()->after('back_canvas_state');
            $table->string('back_final_path')->nullable()->after('front_final_path');

            // Link to order (when design is ordered)
            $table->foreignId('order_id')->nullable()->after('status')->constrained()->onDelete('set null');

            // Track if design was modified after ordering
            $table->timestamp('ordered_at')->nullable()->after('order_id');
        });

        // Add design_id to order_items for tracking which design was ordered
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('design_id')->nullable()->after('product_id')->constrained('user_designs')->onDelete('set null');
            // Store the final design image paths in order_items for historical reference
            $table->string('design_front_image')->nullable()->after('product_attributes');
            $table->string('design_back_image')->nullable()->after('design_front_image');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['design_id']);
            $table->dropColumn(['design_id', 'design_front_image', 'design_back_image']);
        });

        Schema::table('user_designs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn([
                'front_canvas_state',
                'back_canvas_state',
                'front_final_path',
                'back_final_path',
                'order_id',
                'ordered_at'
            ]);
        });
    }
};
