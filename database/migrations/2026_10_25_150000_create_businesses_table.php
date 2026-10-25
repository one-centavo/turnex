<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
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
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('business_name', 150);
            $table->string('business_category', 50);
            $table->string('owner_first_name', 60);
            $table->string('owner_middle_name', 60)->nullable();
            $table->string('owner_last_name', 60);
            $table->string('owner_second_last_name', 60)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone_number', 20)->unique();
            $table->string('location', 255);
            $table->string('logo_path', 255)->nullable();
            $table->string('cover_photo_path', 255)->nullable();
            $table->string('business_description', 500)->nullable();
            $table->string('verification_status', 30)->default(VerificationStatus::PendingVerification->value);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
