<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Customer;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mi Turno Digital')] #[Layout('layouts.storefront')] class extends Component
{
    public string $code = '';

    public ?int $ticketId = null;

    public function mount(string $code, QueueService $queueService): void
    {
        $ticket = QueueTicket::query()
            ->with(['business', 'items.offering', 'customer'])
            ->where('ticket_code', $code)
            ->first();

        if ($ticket === null) {
            abort(404);
        }

        $this->code = $code;
        $this->ticketId = $ticket->id;
    }

    #[Computed]
    public function ticket(): ?QueueTicket
    {
        if ($this->ticketId === null) {
            return null;
        }

        /** @var QueueTicket|null */
        return QueueTicket::query()
            ->with(['business', 'items.offering', 'customer'])
            ->find($this->ticketId);
    }

    #[Computed]
    public function turnsAhead(?QueueService $queueService = null): int
    {
        if ($this->ticket === null) {
            return 0;
        }

        $queueService ??= app(QueueService::class);

        return $queueService->calculateQueuePosition($this->ticket);
    }

    public function cancelTurn(QueueService $queueService): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($customer === null || $this->ticket === null || $customer->id !== $this->ticket->customer_id) {
            abort(403, 'No autorizado.');
        }

        try {
            $queueService->cancelTicket($this->ticket, $customer);
            $this->modal('confirm-cancel-turn-modal')->close();
            Flux::toast(variant: 'success', text: __('Turno cancelado exitosamente.'));
        } catch (\DomainException $e) {
            $this->modal('confirm-cancel-turn-modal')->close();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
};
?>

<div wire:poll.5s class="min-h-screen flex flex-col bg-surface-canvas">
    {{-- Top Navigation Bar --}}
    <header class="w-full border-b border-border-subtle bg-surface-card/80 backdrop-blur-md sticky top-0 z-30">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="group inline-flex items-center gap-2 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-primary rounded-lg p-1">
                <x-app-logo-icon class="size-7 fill-current text-brand-primary" />
                <span class="font-black tracking-tight text-xl text-text-primary leading-none">
                    TURN<span class="text-brand-primary">EX</span>
                </span>
            </a>

            @if ($this->ticket && $this->ticket->business)
                <flux:button
                    href="{{ route('storefront.show', $this->ticket->business->slug) }}"
                    variant="subtle"
                    size="sm"
                    icon="building-storefront"
                >
                    {{ __('Ver Catálogo') }}
                </flux:button>
            @endif
        </div>
    </header>

    {{-- Main Container --}}
    <main class="flex-1 py-8 sm:py-12 px-4 sm:px-6">
        <div class="max-w-lg mx-auto space-y-6">
            @if ($this->ticket)
                {{-- Business Header Card --}}
                <div class="flex items-center gap-4 rounded-2xl bg-surface-card border border-border-subtle p-4 shadow-xs">
                    <div class="shrink-0">
                        @if ($this->ticket->business->logo_path)
                            <img
                                src="{{ Storage::url($this->ticket->business->logo_path) }}"
                                alt="{{ $this->ticket->business->business_name }}"
                                class="size-14 rounded-xl object-cover border border-border-subtle bg-surface-subtle"
                            />
                        @else
                            <div class="flex size-14 items-center justify-center rounded-xl bg-surface-subtle text-text-primary font-bold text-lg border border-border-subtle">
                                {{ strtoupper(substr($this->ticket->business->business_name, 0, 2)) }}
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <span class="text-xs font-semibold text-text-secondary uppercase tracking-wider block">
                            {{ __('Fila virtual en') }}
                        </span>
                        <h2 class="text-lg font-bold text-text-primary truncate">
                            {{ $this->ticket->business->business_name }}
                        </h2>
                    </div>

                    <a
                        href="{{ route('storefront.show', $this->ticket->business->slug) }}"
                        class="text-text-secondary hover:text-text-primary p-2 rounded-lg hover:bg-surface-subtle transition-colors"
                        title="{{ __('Ir a la tienda') }}"
                    >
                        <flux:icon.arrow-top-right-on-square class="size-5" />
                    </a>
                </div>

                {{-- Central Ticket Card --}}
                <div class="rounded-3xl bg-surface-card border border-border-subtle shadow-md p-6 sm:p-8 text-center space-y-6">
                    <div class="space-y-2">
                        <span class="text-xs uppercase tracking-widest text-text-secondary font-semibold">
                            {{ __('Tu Turno') }}
                        </span>
                        <div class="text-5xl sm:text-6xl font-black text-text-primary tracking-tight font-mono">
                            {{ $this->ticket->ticket_number }}
                        </div>
                    </div>

                    <div>
                        <flux:badge :color="$this->ticket->status->color()" size="lg">
                            {{ $this->ticket->status->label() }}
                        </flux:badge>
                    </div>

                    {{-- Dynamic Status Indicators --}}
                    @if ($this->ticket->status === QueueTicketStatus::Pending)
                        @if ($this->turnsAhead === 0)
                            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-center">
                                <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ __('¡Eres el siguiente!') }}
                                </p>
                                <p class="text-xs text-text-secondary mt-1">
                                    {{ __('Mantente atento a la pantalla de llamados o a este dispositivo.') }}
                                </p>
                            </div>
                        @else
                            <div class="rounded-2xl border border-border-subtle bg-surface-subtle p-5 text-center">
                                <div class="text-3xl font-black text-text-primary">
                                    {{ $this->turnsAhead }}
                                </div>
                                <p class="text-xs font-medium text-text-secondary mt-1">
                                    {{ $this->turnsAhead === 1 ? __('persona antes de ti') : __('personas antes de ti') }}
                                </p>
                            </div>
                        @endif
                    @elseif ($this->ticket->status === QueueTicketStatus::Preparing)
                        <div class="rounded-2xl border border-sky-500/30 bg-sky-500/10 p-5 text-center">
                            <flux:icon.arrow-path class="size-6 text-sky-500 mx-auto mb-2 animate-spin" />
                            <p class="text-sm font-semibold text-sky-600 dark:text-sky-400">
                                {{ __('Tu pedido se encuentra en preparación') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::Ready)
                        <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-center animate-pulse">
                            <flux:icon.check-circle class="size-8 text-emerald-500 mx-auto mb-2" />
                            <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                {{ __('¡Tu pedido está listo para recoger en mostrador!') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::Called)
                        <div class="rounded-2xl border border-indigo-500/30 bg-indigo-500/10 p-5 text-center animate-pulse">
                            <flux:icon.speaker-wave class="size-8 text-indigo-500 mx-auto mb-2" />
                            <p class="text-base font-bold text-indigo-600 dark:text-indigo-400">
                                {{ __('¡Es tu turno! Acércate al módulo de atención') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::InService)
                        <div class="rounded-2xl border border-blue-500/30 bg-blue-500/10 p-5 text-center">
                            <flux:icon.user-circle class="size-7 text-blue-500 mx-auto mb-2" />
                            <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                {{ __('En este momento estás siendo atendido') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::Skipped)
                        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5 text-center">
                            <flux:icon.exclamation-triangle class="size-7 text-amber-500 mx-auto mb-2" />
                            <p class="text-sm font-semibold text-amber-600 dark:text-amber-400">
                                {{ __('Tu turno fue pospuesto porque no te encontrabas presente. Por favor acércate al personal') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::Completed)
                        <div class="rounded-2xl border border-border-subtle bg-surface-subtle p-5 text-center">
                            <flux:icon.check class="size-7 text-text-secondary mx-auto mb-2" />
                            <p class="text-sm font-semibold text-text-primary">
                                {{ __('Este turno fue completado. ¡Gracias por tu visita!') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::NoShow)
                        <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-5 text-center">
                            <flux:icon.x-circle class="size-7 text-rose-500 mx-auto mb-2" />
                            <p class="text-sm font-semibold text-rose-600 dark:text-rose-400">
                                {{ __('Este turno expiró por inasistencia') }}
                            </p>
                        </div>
                    @elseif ($this->ticket->status === QueueTicketStatus::Cancelled)
                        <div class="rounded-2xl border border-border-subtle bg-surface-subtle p-5 text-center">
                            <flux:icon.x-mark class="size-7 text-text-secondary mx-auto mb-2" />
                            <p class="text-sm font-semibold text-text-secondary">
                                {{ __('Este turno fue cancelado') }}
                            </p>
                        </div>
                    @endif

                    <div class="text-xs text-text-secondary">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500 animate-ping"></span>
                            {{ __('Actualización en tiempo real activa') }}
                        </span>
                    </div>
                </div>

                {{-- Order Details Section --}}
                <div class="rounded-2xl bg-surface-card border border-border-subtle shadow-xs p-5 sm:p-6 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-secondary">
                        {{ __('Detalle de la orden') }}
                    </h3>

                    <div class="divide-y divide-border-subtle">
                        @foreach ($this->ticket->items as $item)
                            <div class="flex items-center justify-between py-2.5 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex size-6 items-center justify-center rounded-md bg-surface-subtle text-xs font-bold text-text-primary border border-border-subtle">
                                        {{ $item->quantity }}x
                                    </span>
                                    <span class="font-medium text-text-primary">
                                        {{ $item->offering?->name ?? __('Producto') }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="font-semibold text-text-primary">
                                        $ {{ number_format((float) $item->unit_price * $item->quantity, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between border-t border-border-subtle pt-3 text-base">
                        <span class="font-medium text-text-secondary">{{ __('Total de la orden') }}</span>
                        <span class="font-extrabold text-text-primary">
                            $ {{ number_format((float) $this->ticket->total_price, 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between border-t border-border-subtle pt-2 text-xs text-text-secondary">
                        <span>{{ __('Fecha y hora') }}</span>
                        <span>{{ $this->ticket->created_at?->format('d/m/Y h:i A') }}</span>
                    </div>
                </div>

                {{-- Voluntary Cancellation --}}
                @if ($this->ticket->isActive() && auth('customer')->id() === $this->ticket->customer_id)
                    <div class="pt-2 text-center">
                        <flux:modal.trigger name="confirm-cancel-turn-modal">
                            <flux:button variant="ghost" class="text-rose-600 hover:text-rose-700 dark:text-rose-400 cursor-pointer text-xs sm:text-sm">
                                {{ __('Cancelar turno') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:modal name="confirm-cancel-turn-modal" class="max-w-md">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('¿Deseas cancelar tu turno?') }}</flux:heading>
                                <flux:subheading class="mt-1 text-text-secondary">
                                    {{ __('Al cancelar tu turno perderás tu posición en la fila y tu pedido no será procesado.') }}
                                </flux:subheading>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button variant="ghost" class="cursor-pointer">
                                        {{ __('No, mantener mi turno') }}
                                    </flux:button>
                                </flux:modal.close>

                                <flux:button
                                    variant="danger"
                                    wire:click="cancelTurn"
                                    wire:loading.attr="disabled"
                                    class="cursor-pointer"
                                >
                                    <span wire:loading.remove wire:target="cancelTurn">
                                        {{ __('Sí, cancelar turno') }}
                                    </span>
                                    <span wire:loading wire:target="cancelTurn" class="inline-flex items-center gap-2">
                                        <flux:icon.arrow-path class="size-4 animate-spin" />
                                        {{ __('Cancelando...') }}
                                    </span>
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @endif

                {{-- Footer Navigation Links --}}
                <div class="pt-4 flex items-center justify-center gap-4 text-xs text-text-secondary">
                    <a href="{{ route('storefront.show', $this->ticket->business->slug) }}" class="inline-flex items-center gap-1.5 hover:text-text-primary transition-colors">
                        <flux:icon.arrow-left class="size-3.5" />
                        <span>{{ __('Volver al catálogo') }}</span>
                    </a>
                    <span>&bull;</span>
                    <a href="{{ route('home') }}" class="hover:text-text-primary transition-colors">
                        <span>{{ __('Inicio') }}</span>
                    </a>
                </div>
            @endif
        </div>
    </main>
</div>
