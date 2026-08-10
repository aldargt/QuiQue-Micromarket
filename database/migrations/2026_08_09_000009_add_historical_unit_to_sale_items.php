<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit', 30)->nullable()->after('product_name');
        });

        DB::table('sale_items')->orderBy('id')->eachById(function ($item): void {
            $unit = DB::table('products')->where('id', $item->product_id)->value('unit');
            DB::table('sale_items')->where('id', $item->id)->update(['unit' => $unit]);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
