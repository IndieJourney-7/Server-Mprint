<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a banner_image column for category page hero banners
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('banner_image')->nullable()->after('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('banner_image');
        });
    }
};
