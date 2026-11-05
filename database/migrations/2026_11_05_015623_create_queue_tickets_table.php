<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
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
        Schema::create('queue_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('ticket_number', 10);
            $table->string('ticket_code', 32)->unique();
            $table->string('status', 20)->default(QueueTicketStatus::Pending->value);
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->timestamp('called_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at', 'ticket_number']);
            $table->index(['business_id', 'customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
