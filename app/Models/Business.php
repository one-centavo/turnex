<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessCategory;
use App\Enums\PersonType;
use App\Enums\VerificationStatus;
use Carbon\CarbonInterface;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $reviewed_by
 * @property PersonType $person_type
 * @property string|null $tax_id
 * @property string|null $dv
 * @property string $business_name
 * @property BusinessCategory $business_category
 * @property string $owner_first_name
 * @property string|null $owner_middle_name
 * @property string $owner_last_name
 * @property string|null $owner_second_last_name
 * @property string $email
 * @property string $phone_number
 * @property string $location
 * @property string|null $logo_path
 * @property string|null $cover_photo_path
 * @property string|null $business_description
 * @property VerificationStatus $verification_status
 * @property string|null $rejection_reason
 * @property CarbonInterface|null $email_verified_at
 * @property CarbonInterface|null $phone_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonInterface|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $reviewer
 */
#[Fillable([
    'reviewed_by',
    'person_type',
    'tax_id',
    'dv',
    'business_name',
    'business_category',
    'owner_first_name',
    'owner_middle_name',
    'owner_last_name',
    'owner_second_last_name',
    'email',
    'phone_number',
    'location',
    'logo_path',
    'cover_photo_path',
    'business_description',
    'verification_status',
    'rejection_reason',
    'email_verified_at',
    'phone_verified_at',
    'password',
    'reviewed_at',
])]
#[Hidden(['password', 'remember_token'])]
class Business extends Authenticatable
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'verification_status' => VerificationStatus::class,
            'business_category' => BusinessCategory::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the system user who reviewed this business.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the owner's full name.
     */
    public function ownerFullName(): string
    {
        $parts = array_filter([
            $this->owner_first_name,
            $this->owner_middle_name,
            $this->owner_last_name,
            $this->owner_second_last_name,
        ], fn (?string $part): bool => filled($part));

        return implode(' ', $parts);
    }

    /**
     * Determine if the business has a verified phone number.
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Determine if the business has a verified email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Determine if the business is pending verification.
     */
    public function isPendingVerification(): bool
    {
        return $this->verification_status === VerificationStatus::PendingVerification;
    }

    /**
     * Determine if the business is currently under review.
     */
    public function isUnderReview(): bool
    {
        return $this->verification_status === VerificationStatus::UnderReview;
    }

    /**
     * Determine if the business is approved.
     */
    public function isApproved(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    /**
     * Determine if action is required from the business.
     */
    public function isActionRequired(): bool
    {
        return $this->verification_status === VerificationStatus::ActionRequired;
    }

    /**
     * Determine if the business verification has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->verification_status === VerificationStatus::Rejected;
    }

    /**
     * Determine if the business is registered as a natural person.
     */
    public function isNaturalPerson(): bool
    {
        return $this->person_type === PersonType::Natural;
    }

    /**
     * Determine if the business is registered as a legal entity.
     */
    public function isLegalEntity(): bool
    {
        return $this->person_type === PersonType::LegalEntity;
    }

    /**
     * Get the formatted tax identification with verification digit (e.g. 901234567-3).
     */
    public function formattedTaxId(): ?string
    {
        if ($this->tax_id === null) {
            return null;
        }

        return $this->dv !== null ? "{$this->tax_id}-{$this->dv}" : $this->tax_id;
    }
}
