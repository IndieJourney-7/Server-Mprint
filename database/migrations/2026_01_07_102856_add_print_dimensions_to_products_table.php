<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds print dimension fields for dynamic canvas sizing.
     * Dimensions are stored in INCHES (industry standard for print).
     * Example: Business card = 3.5 x 2 inches
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Print dimensions in inches (e.g., 3.5 for 3.5 inches)
            $table->decimal('print_length_inches', 5, 2)->nullable()->after('dimensions');
            $table->decimal('print_width_inches', 5, 2)->nullable()->after('print_length_inches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['print_length_inches', 'print_width_inches']);
        });
    }
};
