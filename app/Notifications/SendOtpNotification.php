<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ContactType;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
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
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->channel) {
            ContactType::Email => ['mail'],
            ContactType::Sms => [SmsChannel::class],
            ContactType::WhatsApp => [WhatsAppChannel::class],
        };
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        return __(':app: Tu código de verificación es :code. Válido por 5 minutos. No lo compartas con nadie.', [
            'app' => (string) config('app.name'),
            'code' => $this->code,
        ]);
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): string
    {
        return __(':app: Tu código de verificación es :code. Válido por 5 minutos. No lo compartas con nadie.', [
            'app' => (string) config('app.name'),
            'code' => $this->code,
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Tu código de verificación de Turnex'))
            ->greeting(__('¡Hola!'))
            ->line(__('Usa el siguiente código de 6 dígitos para completar tu verificación:'))
            ->line(new HtmlString(
                "<div style=\"font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center; padding: 16px 0; color: #4F46E5;\">{$this->code}</div>"
            ))
            ->line(__('Este código es válido por 5 minutos y solo puede ser utilizado una vez.'))
            ->line(__('Si no solicitaste este código, no se requiere ninguna acción. Por tu seguridad, nunca compartas este código con nadie.'));
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
