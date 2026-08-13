<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('raffle_ticket_threshold', 12, 2)->default(50)->after('is_active');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('full_name', 150);
            $table->string('phone', 30);
            $table->string('ci', 30)->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'phone']);
            $table->unique(['branch_id', 'ci']);
            $table->index(['branch_id', 'full_name']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('raffle_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['branch_id', 'starts_on']);
            $table->index(['branch_id', 'ends_on']);
        });

        Schema::create('raffle_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('raffle_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('threshold_amount', 12, 2);
            $table->unsignedInteger('eligible_ticket_count');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('raffle_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('raffle_participation_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('raffle_period_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number', 50)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(['branch_id', 'customer_id']);
            $table->index(['raffle_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_tickets');
        Schema::dropIfExists('raffle_participations');
        Schema::dropIfExists('raffle_periods');
        Schema::table('sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('customers');
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('raffle_ticket_threshold'));
    }
};
