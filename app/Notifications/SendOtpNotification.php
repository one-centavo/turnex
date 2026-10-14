<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ContactType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $code,
        public readonly ContactType $channel,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->channel) {
            ContactType::Email => ['mail'],
            ContactType::Sms => ['sms'],
            ContactType::WhatsApp => ['whatsapp'],
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Turnex Verification Code')
            ->line("Your one-time security code is: {$this->code}")
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->code,
            'channel' => $this->channel->value,
        ];
    }
}
