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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('email', 255)->unique();
            $table->string('phone_number', 20)->nullable()->unique();
            $table->string('password', 255)->nullable();
            $table->string('google_id', 255)->nullable()->unique();
            $table->string('avatar_url', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
