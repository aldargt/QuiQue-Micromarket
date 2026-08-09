<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array<string>> */
    private array $columnsByTable = [
        'roles' => ['created_at', 'updated_at'],
        'branches' => ['created_at', 'updated_at'],
        'users' => ['email_verified_at', 'created_at', 'updated_at'],
        'categories' => ['created_at', 'updated_at'],
        'products' => ['created_at', 'updated_at'],
        'sales' => ['confirmed_at', 'created_at', 'updated_at'],
        'sale_items' => ['created_at', 'updated_at'],
        'inventory_movements' => ['created_at', 'updated_at'],
        'payment_details' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->convert('UTC', 'America/La_Paz');
    }

    public function down(): void
    {
        $this->convert('America/La_Paz', 'UTC');
    }

    private function convert(string $from, string $to): void
    {
        foreach ($this->columnsByTable as $table => $columns) {
            DB::table($table)->orderBy('id')->eachById(function (object $row) use ($table, $columns, $from, $to): void {
                $updates = [];

                foreach ($columns as $column) {
                    if ($row->{$column} !== null) {
                        $updates[$column] = CarbonImmutable::createFromFormat(
                            'Y-m-d H:i:s',
                            (string) $row->{$column},
                            $from,
                        )->setTimezone($to)->format('Y-m-d H:i:s');
                    }
                }

                if ($updates !== []) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            });
        }
    }
};
