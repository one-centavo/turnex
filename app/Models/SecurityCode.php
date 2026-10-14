<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactType;
use Database\Factories\SecurityCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $contact_value
 * @property ContactType $contact_type
 * @property string $code
 * @property int $attempts_count
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'contact_value',
    'contact_type',
    'code',
    'attempts_count',
    'expires_at',
    'used_at',
])]
#[Hidden(['code'])]
class SecurityCode extends Model
{
    /** @use HasFactory<SecurityCodeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'contact_type' => ContactType::class,
            'attempts_count' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the security code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine whether the security code has already been used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Determine whether the maximum verification attempts have been reached.
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts_count >= 5;
    }
}
