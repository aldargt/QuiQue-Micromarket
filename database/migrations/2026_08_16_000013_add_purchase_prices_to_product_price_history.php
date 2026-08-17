<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->decimal('old_purchase_price', 12, 2)->nullable()->after('user_id');
            $table->decimal('new_purchase_price', 12, 2)->nullable()->after('old_purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->dropColumn(['old_purchase_price', 'new_purchase_price']);
        });
    }
};
