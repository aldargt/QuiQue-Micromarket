<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('internal_code', 50)->nullable()->change();
        });

        DB::table('products')->whereNotNull('barcode')->update(['internal_code' => null]);
    }

    public function down(): void
    {
        DB::table('products')
            ->whereNull('internal_code')
            ->orderBy('id')
            ->eachById(function ($product): void {
                DB::table('products')->where('id', $product->id)->update([
                    'internal_code' => 'PRD-LEGACY-'.$product->id,
                ]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->string('internal_code', 50)->nullable(false)->change();
        });
    }
};
