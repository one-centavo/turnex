<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;

test('activeStatuses helper returns all 6 active statuses and excludes terminal statuses', function (): void {
    $activeStatuses = QueueTicketStatus::activeStatuses();

    expect($activeStatuses)->toBeArray()
        ->and($activeStatuses)->toHaveCount(6)
        ->and($activeStatuses)->toBe([
            QueueTicketStatus::Pending,
            QueueTicketStatus::Preparing,
            QueueTicketStatus::Ready,
            QueueTicketStatus::Called,
            QueueTicketStatus::InService,
            QueueTicketStatus::Skipped,
        ])
        ->and($activeStatuses)->not->toContain(QueueTicketStatus::Completed)
        ->and($activeStatuses)->not->toContain(QueueTicketStatus::NoShow)
        ->and($activeStatuses)->not->toContain(QueueTicketStatus::Cancelled);

    foreach ($activeStatuses as $status) {
        expect($status->isActive())->toBeTrue()
            ->and($status->isTerminal())->toBeFalse();
    }
});
