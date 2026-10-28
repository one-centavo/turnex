<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeBusinessNotification extends Notification implements ShouldQueue
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
        $ownerName = $notifiable instanceof Business ? $notifiable->owner_first_name : 'Comerciante';
        $businessName = $notifiable instanceof Business ? $notifiable->business_name : 'tu negocio';

        return (new MailMessage)
            ->subject(__('¡Bienvenido a :app, :business!', ['app' => $appName, 'business' => $businessName]))
            ->greeting(__('¡Hola, :name!', ['name' => $ownerName]))
            ->line(__('El perfil de tu negocio **:business** ha sido registrado exitosamente en :app.', ['business' => $businessName, 'app' => $appName]))
            ->line(__('Tu correo electrónico ha sido verificado exitosamente. Tu cuenta se encuentra en estado inicial **Pendiente de Verificación** mientras configuras tu catálogo y sucursales.'))
            ->action(__('Ir a mi Panel de Negocio'), route('dashboard'))
            ->line(__('Gracias por confiar en :app para optimizar la atención de tus clientes.', ['app' => $appName]));
    }
}
