<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name', 'Turnex');
        $firstName = $notifiable instanceof Customer ? $notifiable->first_name : 'Cliente';

        return (new MailMessage)
            ->subject(__('¡Bienvenido a :app!', ['app' => $appName]))
            ->greeting(__('¡Hola, :name!', ['name' => $firstName]))
            ->line(__('Tu cuenta ha sido creada exitosamente. Tu número de teléfono celular ya se encuentra certificado en nuestra plataforma.'))
            ->action(__('Ir a la plataforma'), route('home'))
            ->line(__('Gracias por hacer parte de :app.', ['app' => $appName]));
    }
}
