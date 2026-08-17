<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('confirmed_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by')->index();
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at');
            $table->index(['branch_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
