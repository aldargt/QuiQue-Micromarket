<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('sale_number', 50)->change();
            $table->decimal('subtotal', 12, 2)->default(0)->after('sale_number');
            $table->string('status', 20)->default('confirmed')->after('total')->index();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unique(['sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropUnique(['sale_id', 'product_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'status']);
            $table->string('sale_number', 20)->change();
        });
    }
};
