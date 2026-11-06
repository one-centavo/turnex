<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Enums\QueueTicketStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Services\ExploreBusinessesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('getPaginatedBusinesses only returns approved businesses with active offerings', function (): void {
    $service = new ExploreBusinessesService;

    // 1. Approved with active offering -> included
    $approvedWithOffering = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Valid Bakery']);

    // 2. Pending verification -> excluded
    Business::factory()
        ->state(['verification_status' => VerificationStatus::PendingVerification])
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Pending Cafe']);

    // 3. Under review -> excluded
    Business::factory()
        ->underReview()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Under Review Shop']);

    // 4. Rejected -> excluded
    Business::factory()
        ->rejected()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Rejected Store']);

    // 5. Approved with NO offerings -> excluded
    Business::factory()
        ->approved()
        ->create(['business_name' => 'Empty Salon']);

    // 6. Approved with only inactive offerings -> excluded
    Business::factory()
        ->approved()
        ->has(Offering::factory()->inactive())
        ->create(['business_name' => 'Inactive Salon']);

    // 7. Approved with soft-deleted offering -> excluded
    $softDeletedOfferingBusiness = Business::factory()
        ->approved()
        ->create(['business_name' => 'Deleted Offering Spa']);
    $offering = Offering::factory()->for($softDeletedOfferingBusiness)->create(['is_active' => true]);
    $offering->delete();

    $results = $service->getPaginatedBusinesses();

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($approvedWithOffering->id);
});

test('getPaginatedBusinesses accurately counts active tickets and ignores terminal tickets', function (): void {
    $service = new ExploreBusinessesService;

    $business = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Busy Clinic']);

    // Active tickets (should be counted)
    QueueTicket::factory()->for($business)->pending()->create();
    QueueTicket::factory()->for($business)->preparing()->create();
    QueueTicket::factory()->for($business)->ready()->create();
    QueueTicket::factory()->for($business)->called()->create();
    QueueTicket::factory()->for($business)->inService()->create();
    QueueTicket::factory()->for($business)->skipped()->create();

    // Terminal tickets (should NOT be counted)
    QueueTicket::factory()->for($business)->completed()->create();
    QueueTicket::factory()->for($business)->noShow()->create();
    QueueTicket::factory()->for($business)->cancelled()->create();

    $results = $service->getPaginatedBusinesses();

    expect($results->total())->toBe(1)
        ->and((int) $results->items()[0]->active_tickets_count)->toBe(6);
});

test('getPaginatedBusinesses orders results by active tickets descending and business name ascending', function (): void {
    $service = new ExploreBusinessesService;

    $businessA = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Alpha Market']);

    $businessB = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Beta Market']);

    $businessC = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_name' => 'Crowded Market']);

    // Business C has 2 active tickets
    QueueTicket::factory()->for($businessC)->pending()->count(2)->create();

    // Business B has 1 active ticket
    QueueTicket::factory()->for($businessB)->pending()->count(1)->create();

    // Business A has 0 active tickets

    $results = $service->getPaginatedBusinesses();

    expect($results->total())->toBe(3)
        ->and($results->items()[0]->id)->toBe($businessC->id)
        ->and($results->items()[1]->id)->toBe($businessB->id)
        ->and($results->items()[2]->id)->toBe($businessA->id);
});

test('getPaginatedBusinesses filters by search term across business name and location', function (): void {
    $service = new ExploreBusinessesService;

    $businessA = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create([
            'business_name' => 'Pizzeria Bella',
            'location' => 'Calle 10 # 5-20',
        ]);

    $businessB = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create([
            'business_name' => 'Sushi Bar',
            'location' => 'Carrera 15 # 80-10',
        ]);

    // Search by name
    $resultsByName = $service->getPaginatedBusinesses(search: 'Bella');
    expect($resultsByName->total())->toBe(1)
        ->and($resultsByName->items()[0]->id)->toBe($businessA->id);

    // Search by location
    $resultsByLocation = $service->getPaginatedBusinesses(search: 'Carrera 15');
    expect($resultsByLocation->total())->toBe(1)
        ->and($resultsByLocation->items()[0]->id)->toBe($businessB->id);

    // Search with surrounding whitespace
    $resultsWhitespace = $service->getPaginatedBusinesses(search: '   Sushi   ');
    expect($resultsWhitespace->total())->toBe(1)
        ->and($resultsWhitespace->items()[0]->id)->toBe($businessB->id);

    // Search with empty or whitespace-only returns all
    $resultsEmpty = $service->getPaginatedBusinesses(search: '     ');
    expect($resultsEmpty->total())->toBe(2);

    // Search with special wildcard characters does not match unintentionally
    $wildcardResults = $service->getPaginatedBusinesses(search: '%');
    expect($wildcardResults->total())->toBe(0);
});

test('getPaginatedBusinesses filters by business category', function (): void {
    $service = new ExploreBusinessesService;

    $restaurant = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_category' => BusinessCategory::Restaurant]);

    $salon = Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->create(['business_category' => BusinessCategory::HealthAndBeauty]);

    $restaurantResults = $service->getPaginatedBusinesses(category: BusinessCategory::Restaurant->value);
    expect($restaurantResults->total())->toBe(1)
        ->and($restaurantResults->items()[0]->id)->toBe($restaurant->id);

    $salonResults = $service->getPaginatedBusinesses(category: BusinessCategory::HealthAndBeauty->value);
    expect($salonResults->total())->toBe(1)
        ->and($salonResults->items()[0]->id)->toBe($salon->id);

    // Invalid category should be ignored and return all approved businesses
    $invalidCategoryResults = $service->getPaginatedBusinesses(category: 'invalid_category_xyz');
    expect($invalidCategoryResults->total())->toBe(2);
});

test('getPaginatedBusinesses respects perPage pagination argument', function (): void {
    $service = new ExploreBusinessesService;

    Business::factory()
        ->approved()
        ->has(Offering::factory()->active())
        ->count(15)
        ->create();

    $results = $service->getPaginatedBusinesses(perPage: 5);

    expect($results->total())->toBe(15)
        ->and($results->perPage())->toBe(5)
        ->and(count($results->items()))->toBe(5);
});

test('getActiveCustomerBusinessIds returns flipped business IDs for customer active tickets', function (): void {
    $service = new ExploreBusinessesService;

    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $businessA = Business::factory()->approved()->create();
    $businessB = Business::factory()->approved()->create();
    $businessC = Business::factory()->approved()->create();

    // Customer has active ticket in business A and B
    QueueTicket::factory()->for($businessA)->for($customer)->status(QueueTicketStatus::Pending)->create();
    QueueTicket::factory()->for($businessB)->for($customer)->status(QueueTicketStatus::InService)->create();

    // Customer has completed ticket in business C (should not be included)
    QueueTicket::factory()->for($businessC)->for($customer)->status(QueueTicketStatus::Completed)->create();

    // Other customer has active ticket in business C (should not be included for this customer)
    QueueTicket::factory()->for($businessC)->for($otherCustomer)->status(QueueTicketStatus::Pending)->create();

    $activeBusinessIds = $service->getActiveCustomerBusinessIds($customer->id);

    expect($activeBusinessIds)->toBeArray()
        ->and(array_key_exists($businessA->id, $activeBusinessIds))->toBeTrue()
        ->and(array_key_exists($businessB->id, $activeBusinessIds))->toBeTrue()
        ->and(array_key_exists($businessC->id, $activeBusinessIds))->toBeFalse();

    // Null customerId returns empty array
    expect($service->getActiveCustomerBusinessIds(null))->toBe([]);
});
