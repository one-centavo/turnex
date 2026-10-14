<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ContactType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

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
            ->subject(__('Your Turnex Verification Code'))
            ->greeting(__('Hello!'))
            ->line(__('Use the following 6-digit code to complete your verification:'))
            ->line(new HtmlString(
                "<div style=\"font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center; padding: 16px 0; color: #4F46E5;\">{$this->code}</div>"
            ))
            ->line(__('This code is valid for 5 minutes and can only be used once.'))
            ->line(__('If you did not request this code, no action is needed. For your security, never share this code with anyone.'));
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
