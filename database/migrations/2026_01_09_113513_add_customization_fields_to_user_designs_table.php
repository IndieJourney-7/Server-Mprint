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
        Schema::table('user_designs', function (Blueprint $table) {
            // Allow guest users to save designs using session ID
            $table->string('session_id')->nullable()->after('user_id');

            // Product customization options selected before design upload
            $table->integer('quantity')->default(1)->after('product_id');
            $table->string('selected_shape')->nullable()->after('quantity');
            $table->string('selected_finishing')->nullable()->after('selected_shape');

            // Make user_id nullable for guest users
            $table->foreignId('user_id')->nullable()->change();

            // Add index for session_id lookups
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_designs', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropColumn(['session_id', 'quantity', 'selected_shape', 'selected_finishing']);
        });
    }
};
