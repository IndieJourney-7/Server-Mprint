<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Add design_id to link with user_designs table
            $table->foreignId('design_id')->nullable()->after('product_id')->constrained('user_designs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['design_id']);
            $table->dropColumn('design_id');
        });
    }
};
