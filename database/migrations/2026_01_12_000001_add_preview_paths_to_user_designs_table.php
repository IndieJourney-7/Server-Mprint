<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds preview paths and text layers fields to user_designs table.
     *
     * These fields are CRITICAL for the Cart page to display the correct design:
     * - front_preview_path / back_preview_path: Store the final preview images WITH text layers baked in
     * - front_text_layers / back_text_layers: Store the text layer data as JSON for future editing
     */
    public function up(): void
    {
        Schema::table('user_designs', function (Blueprint $table) {
            // Preview paths - These store the final preview images with text layers rendered
            // This is what gets displayed in the Cart page
            $table->string('front_preview_path')->nullable()->after('front_canvas_state');
            $table->string('back_preview_path')->nullable()->after('back_canvas_state');

            // Text layers JSON - Store text layer data for potential future editing
            $table->json('front_text_layers')->nullable()->after('front_preview_path');
            $table->json('back_text_layers')->nullable()->after('back_preview_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_designs', function (Blueprint $table) {
            $table->dropColumn([
                'front_preview_path',
                'back_preview_path',
                'front_text_layers',
                'back_text_layers',
            ]);
        });
    }
};
