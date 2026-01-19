<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds design_type field to distinguish between customized and uploaded designs.
     *
     * Design Types:
     * - 'uploaded': User uploaded their own design file
     * - 'customized': User customized a template (Browse Design)
     * - 'blank': User created design from scratch (no template)
     */
    public function up(): void
    {
        Schema::table('user_designs', function (Blueprint $table) {
            // Design type: 'uploaded', 'customized', 'blank'
            $table->enum('design_type', ['uploaded', 'customized', 'blank'])->default('blank')->after('status');

            // Template ID if this design is based on a template
            $table->unsignedBigInteger('template_id')->nullable()->after('design_type');

            // Color variant ID if a specific color variant was selected
            $table->unsignedBigInteger('color_variant_id')->nullable()->after('template_id');

            // Add foreign key constraints
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('set null');
            $table->foreign('color_variant_id')->references('id')->on('template_color_variants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_designs', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['color_variant_id']);
            $table->dropColumn(['design_type', 'template_id', 'color_variant_id']);
        });
    }
};
