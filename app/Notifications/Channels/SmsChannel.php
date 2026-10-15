<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    /**
     * Send the given notification via SMS.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $recipient = $this->resolveRecipient($notifiable);

        if ($recipient === null) {
            return;
        }

        $message = '';

        if (method_exists($notification, 'toSms')) {
            $message = (string) $notification->toSms($notifiable);
        } elseif (method_exists($notification, 'toArray')) {
            /** @var array<string, mixed> $data */
            $data = $notification->toArray($notifiable);
            $message = (string) ($data['code'] ?? '');
        }

        Log::info("SMS OTP dispatched to [{$recipient}]", [
            'recipient' => $recipient,
            'message' => $message,
            'channel' => 'sms',
        ]);
    }

    /**
     * Resolve the phone number recipient from the notifiable entity.
     */
    protected function resolveRecipient(object $notifiable): ?string
    {
        /** @var string|null $route */
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sms') ?? $notifiable->routeNotificationFor(self::class)
            : null;

        if ($route !== null) {
            return $route;
        }

        return property_exists($notifiable, 'phone_number') && is_string($notifiable->phone_number)
            ? $notifiable->phone_number
            : null;
    }
}
