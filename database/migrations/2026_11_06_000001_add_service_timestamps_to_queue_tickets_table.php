<?php

declare(strict_types=1);

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
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->timestamp('served_at')->nullable()->after('called_at');
            $table->timestamp('completed_at')->nullable()->after('served_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            $table->index(['business_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'status', 'created_at']);
            $table->dropColumn(['served_at', 'completed_at', 'cancelled_at']);
        });
    }
};
