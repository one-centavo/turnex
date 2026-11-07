<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Fila en Vivo - Panel')] #[Layout('layouts.business')] class extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'waiting';

    #[Url(as: 'q')]
    public string $search = '';

    public bool $confirmingCallNext = false;

    public ?int $pendingCallTicketId = null;

    public ?int $confirmingNoShowTicketId = null;

    public ?int $confirmingCancelTicketId = null;

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function updatedSearch(): void
    {
        $this->errorMessage = null;
    }

    #[Computed]
    public function business(): Business
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401, 'Negocio no autenticado.');
        }

        return $business;
    }

    #[Computed]
    public function spotlightTicket(): ?QueueTicket
    {
        return app(QueueService::class)->getCurrentSpotlightTicket($this->business);
    }

    /**
     * @return Collection<int, QueueTicket>
     */
    #[Computed]
    public function waitingTickets(): Collection
    {
        return app(QueueService::class)->getWaitingTicketsForBusiness($this->business, $this->search);
    }

    /**
     * @return Collection<int, QueueTicket>
     */
    #[Computed]
    public function postponedTickets(): Collection
    {
        return app(QueueService::class)->getPostponedTicketsForBusiness($this->business);
    }

    /**
     * @return Collection<int, QueueTicket>
     */
    #[Computed]
    public function finishedTickets(): Collection
    {
        return app(QueueService::class)->getFinishedTicketsToday($this->business);
    }

    /**
     * @return array{
     *     waiting: int,
     *     waiting_count: int,
     *     served_today: int,
     *     completed_count: int,
     *     no_show_today: int,
     *     no_show_count: int,
     *     total_today: int
     * }
     */
    #[Computed]
    public function stats(): array
    {
        return app(QueueService::class)->getDailyQueueStats($this->business);
    }

    /**
     * @return array{
     *     waiting: int,
     *     waiting_count: int,
     *     served_today: int,
     *     completed_count: int,
     *     no_show_today: int,
     *     no_show_count: int,
     *     total_today: int
     * }
     */
    #[Computed]
    public function dailyStats(): array
    {
        return $this->stats;
    }

    #[Computed]
    public function isQueuePaused(): bool
    {
        return $this->business->isQueuePaused();
    }

    #[Computed]
    public function ticketToCancel(): ?QueueTicket
    {
        if ($this->confirmingCancelTicketId === null) {
            return null;
        }

        return QueueTicket::query()
            ->where('business_id', $this->business->id)
            ->find($this->confirmingCancelTicketId);
    }

    #[Computed]
    public function ticketForNoShow(): ?QueueTicket
    {
        if ($this->confirmingNoShowTicketId === null) {
            return null;
        }

        return QueueTicket::query()
            ->where('business_id', $this->business->id)
            ->find($this->confirmingNoShowTicketId);
    }

    public function callNext(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket !== null) {
            $this->pendingCallTicketId = null;
            $this->confirmingCallNext = true;
            $this->modal('confirm-call-next-modal')->show();

            return;
        }

        try {
            $ticket = $queueService->callNextTicket($this->business);
            unset($this->spotlightTicket, $this->waitingTickets, $this->stats);

            if ($ticket === null) {
                $msg = __('No hay clientes en espera en este momento.');
                $this->feedbackMessage = $msg;
                Flux::toast(variant: 'warning', text: $msg);
            } else {
                Flux::toast(variant: 'success', text: __('Turno :number llamado al mostrador.', ['number' => $ticket->ticket_number]));
            }
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function confirmCallNext(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;
        $this->modal('confirm-call-next-modal')->close();
        $this->confirmingCallNext = false;

        try {
            if ($current = $this->spotlightTicket) {
                $queueService->postponeTicket($current, $this->business);
            }

            if ($this->pendingCallTicketId !== null) {
                $ticketId = $this->pendingCallTicketId;
                $this->pendingCallTicketId = null;

                $ticket = QueueTicket::query()
                    ->where('business_id', $this->business->id)
                    ->findOrFail($ticketId);

                if ($ticket->status === QueueTicketStatus::Skipped) {
                    $called = $queueService->resumeTicket($ticket, $this->business, callImmediately: true);
                } else {
                    $called = $queueService->callSpecificTicket($this->business, $ticket);
                }

                Flux::toast(variant: 'success', text: __('Turno :number llamado al mostrador.', ['number' => $called->ticket_number]));
            } else {
                $ticket = $queueService->callNextTicket($this->business);

                if ($ticket === null) {
                    $msg = __('No hay clientes en espera en este momento.');
                    $this->feedbackMessage = $msg;
                    Flux::toast(variant: 'warning', text: $msg);
                } else {
                    Flux::toast(variant: 'success', text: __('Turno :number llamado al mostrador.', ['number' => $ticket->ticket_number]));
                }
            }

            unset($this->spotlightTicket, $this->waitingTickets, $this->postponedTickets, $this->finishedTickets, $this->stats);
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function callSpecific(int $ticketId, ?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket !== null) {
            $this->pendingCallTicketId = $ticketId;
            $this->confirmingCallNext = true;
            $this->modal('confirm-call-next-modal')->show();

            return;
        }

        try {
            $ticket = QueueTicket::query()
                ->where('business_id', $this->business->id)
                ->findOrFail($ticketId);

            $called = $queueService->callSpecificTicket($this->business, $ticket);
            unset($this->spotlightTicket, $this->waitingTickets, $this->stats);
            Flux::toast(variant: 'success', text: __('Turno :number llamado al mostrador.', ['number' => $called->ticket_number]));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function recall(?QueueService $queueService = null): void
    {
        $this->recallCustomer($queueService);
    }

    public function recallCustomer(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket === null) {
            return;
        }

        try {
            $queueService->recallTicket($this->spotlightTicket, $this->business);
            unset($this->spotlightTicket);
            Flux::toast(variant: 'success', text: __('Llamado nuevamente al cliente.'));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function startService(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket === null) {
            return;
        }

        try {
            $queueService->startService($this->spotlightTicket, $this->business);
            unset($this->spotlightTicket, $this->stats);
            Flux::toast(variant: 'success', text: __('Atención iniciada.'));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function complete(?QueueService $queueService = null): void
    {
        $this->completeService($queueService);
    }

    public function completeService(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket === null) {
            return;
        }

        try {
            $queueService->completeTicket($this->spotlightTicket, $this->business);
            unset($this->spotlightTicket, $this->finishedTickets, $this->stats);
            Flux::toast(variant: 'success', text: __('Atención completada con éxito.'));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function postpone(?QueueService $queueService = null): void
    {
        $this->postponeCustomer($queueService);
    }

    public function postponeCustomer(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket === null) {
            return;
        }

        try {
            $queueService->postponeTicket($this->spotlightTicket, $this->business);
            unset($this->spotlightTicket, $this->postponedTickets, $this->stats);
            Flux::toast(variant: 'info', text: __('Cliente pospuesto en la fila.'));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function resume(int $ticketId, ?QueueService $queueService = null): void
    {
        $this->resumePostponed($ticketId, $queueService);
    }

    public function resumePostponed(int $ticketId, ?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        if ($this->spotlightTicket !== null) {
            $this->pendingCallTicketId = $ticketId;
            $this->confirmingCallNext = true;
            $this->modal('confirm-call-next-modal')->show();

            return;
        }

        try {
            $ticket = QueueTicket::query()
                ->where('business_id', $this->business->id)
                ->findOrFail($ticketId);

            $resumed = $queueService->resumeTicket($ticket, $this->business, callImmediately: true);
            unset($this->spotlightTicket, $this->postponedTickets, $this->stats);
            Flux::toast(variant: 'success', text: __('Turno :number reanudado y llamado al mostrador.', ['number' => $resumed->ticket_number]));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function promptNoShow(int $ticketId): void
    {
        $this->errorMessage = null;
        $this->confirmingNoShowTicketId = $ticketId;
        $this->modal('confirm-no-show-modal')->show();
    }

    public function markNoShow(?int $ticketId = null, ?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;
        $targetId = $ticketId ?? $this->confirmingNoShowTicketId ?? $this->spotlightTicket?->id;

        if ($targetId === null) {
            return;
        }

        try {
            $ticket = QueueTicket::query()
                ->where('business_id', $this->business->id)
                ->findOrFail($targetId);

            $queueService->markNoShow($ticket, $this->business);
            unset($this->spotlightTicket, $this->finishedTickets, $this->stats);

            if ($this->confirmingNoShowTicketId !== null) {
                $this->modal('confirm-no-show-modal')->close();
                $this->confirmingNoShowTicketId = null;
            }

            Flux::toast(variant: 'warning', text: __('Turno marcado como no se presentó.'));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function confirmNoShow(?QueueService $queueService = null): void
    {
        $this->markNoShow($this->confirmingNoShowTicketId, $queueService);
    }

    public function promptCancel(int $ticketId): void
    {
        $this->errorMessage = null;
        $this->confirmingCancelTicketId = $ticketId;
        $this->modal('confirm-cancel-modal')->show();
    }

    public function cancel(?int $ticketId = null, ?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;
        $targetId = $ticketId ?? $this->confirmingCancelTicketId ?? $this->spotlightTicket?->id;

        if ($targetId === null) {
            return;
        }

        try {
            $ticket = QueueTicket::query()
                ->where('business_id', $this->business->id)
                ->findOrFail($targetId);

            $queueService->cancelTicketByBusiness($ticket, $this->business);
            unset($this->spotlightTicket, $this->waitingTickets, $this->finishedTickets, $this->stats);

            if ($this->confirmingCancelTicketId !== null) {
                $this->modal('confirm-cancel-modal')->close();
                $this->confirmingCancelTicketId = null;
            }

            Flux::toast(variant: 'danger', text: __('Turno cancelado exitosamente.'));
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function confirmCancel(?QueueService $queueService = null): void
    {
        $this->cancel($this->confirmingCancelTicketId, $queueService);
    }

    public function togglePause(?QueueService $queueService = null): void
    {
        $this->toggleQueueReception($queueService);
    }

    public function toggleQueueReception(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $this->errorMessage = null;

        try {
            $newPaused = $queueService->toggleQueuePause($this->business);
            unset($this->business);

            if ($newPaused) {
                Flux::toast(variant: 'warning', text: __('Recepción de turnos pausada.'));
            } else {
                Flux::toast(variant: 'success', text: __('Recepción de turnos reabierta.'));
            }
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
};
?>

<div wire:poll.5s class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
    {{-- Header & Queue Reception Controls --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="font-bold text-text-primary">
                {{ __('Gestión de Turnos en Vivo') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-text-secondary">
                {{ __('Panel de control operativo y atención en mostrador en tiempo real.') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            @if ($this->isQueuePaused)
                <flux:badge color="amber" size="sm" icon="pause-circle">
                    {{ __('Fila Pausada') }}
                </flux:badge>
            @else
                <flux:badge color="emerald" size="sm" icon="check-circle">
                    {{ __('Fila Abierta') }}
                </flux:badge>
            @endif

            <flux:switch
                :checked="$this->isQueuePaused"
                wire:click="togglePause"
                :label="__('Pausar fila')"
                data-test="toggle-queue-pause-switch"
                class="cursor-pointer"
            />
        </div>
    </div>

    {{-- Error / Status Alerts --}}
    @if ($errorMessage)
        <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-red-700 dark:text-red-400 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2">
                <flux:icon.exclamation-triangle class="size-5 shrink-0" />
                <span class="text-sm font-medium">{{ $errorMessage }}</span>
            </div>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-xs font-semibold cursor-pointer underline">
                {{ __('Cerrar') }}
            </button>
        </div>
    @endif

    {{-- Daily Metrics Summary Bar --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 shadow-xs">
            <span class="text-xs sm:text-sm font-medium text-text-secondary">{{ __('En Espera') }}</span>
            <p class="mt-1 text-2xl font-bold text-text-primary" data-test="metric-waiting">
                {{ $this->dailyStats['waiting'] }}
            </p>
        </div>

        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 shadow-xs">
            <span class="text-xs sm:text-sm font-medium text-text-secondary">{{ __('Atendidos Hoy') }}</span>
            <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400" data-test="metric-served">
                {{ $this->dailyStats['served_today'] }}
            </p>
        </div>

        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 shadow-xs">
            <span class="text-xs sm:text-sm font-medium text-text-secondary">{{ __('No se Presentaron') }}</span>
            <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400" data-test="metric-no-show">
                {{ $this->dailyStats['no_show_today'] }}
            </p>
        </div>

        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 shadow-xs">
            <span class="text-xs sm:text-sm font-medium text-text-secondary">{{ __('Total de Turnos Hoy') }}</span>
            <p class="mt-1 text-2xl font-bold text-text-primary" data-test="metric-total">
                {{ $this->dailyStats['total_today'] }}
            </p>
        </div>
    </div>

    {{-- Operational Cockpit Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {{-- Spotlight Hero Column (Left 5-col on desktop) --}}
        <div class="space-y-6 lg:col-span-5">
            @if ($this->spotlightTicket)
                {{-- Active Spotlight Card --}}
                <div class="rounded-2xl border-2 {{ $this->spotlightTicket->isInService() ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-amber-500/50 bg-amber-500/5' }} bg-surface-card p-6 shadow-sm space-y-5" data-test="spotlight-active">
                    <div class="flex items-center justify-between gap-2">
                        @if ($this->spotlightTicket->isInService())
                            <flux:badge color="emerald" size="lg" icon="play">
                                {{ __('En Atención') }}
                            </flux:badge>
                        @else
                            <flux:badge color="amber" size="lg" icon="bell">
                                {{ __('Llamado al Mostrador') }}
                            </flux:badge>
                        @endif

                        <div class="text-end text-xs text-text-secondary">
                            @if ($this->spotlightTicket->isInService() && $this->spotlightTicket->served_at)
                                <span>{{ __('Atención:') }} {{ $this->spotlightTicket->served_at->diffForHumans(null, true) }}</span>
                            @elseif ($this->spotlightTicket->called_at)
                                <span>{{ __('Llamado:') }} {{ $this->spotlightTicket->called_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="font-mono text-3xl sm:text-4xl font-extrabold tracking-tight text-text-primary" data-test="spotlight-ticket-number">
                            {{ $this->spotlightTicket->ticket_number }}
                        </span>
                        <div class="mt-2 flex flex-col gap-1">
                            <span class="text-base font-semibold text-text-primary">
                                {{ $this->spotlightTicket->customer?->fullName() ?? __('Cliente General') }}
                            </span>
                            @if ($this->spotlightTicket->customer?->phone_number)
                                <a href="tel:{{ $this->spotlightTicket->customer->phone_number }}" class="text-sm text-brand-primary underline hover:opacity-80">
                                    {{ $this->spotlightTicket->customer->phone_number }}
                                </a>
                            @endif
                            <span class="text-xs text-text-secondary">
                                {{ __('Espera previa:') }} {{ $this->spotlightTicket->created_at->diffForHumans($this->spotlightTicket->called_at ?? now(), true) }}
                            </span>
                        </div>
                    </div>

                    {{-- Items Order Breakdown --}}
                    @if ($this->spotlightTicket->items->isNotEmpty())
                        <div class="rounded-xl border border-border-subtle bg-surface-subtle p-3 space-y-2">
                            <div class="flex items-center justify-between text-xs font-semibold text-text-secondary border-b border-border-subtle pb-1">
                                <span>{{ __('Pedido / Servicios') }}</span>
                                <span>{{ __('Total:') }} ${{ number_format((float) $this->spotlightTicket->total_price, 2) }}</span>
                            </div>
                            <ul class="divide-y divide-border-subtle text-xs text-text-primary">
                                @foreach ($this->spotlightTicket->items as $item)
                                    <li class="py-1 flex items-center justify-between">
                                        <span>{{ $item->quantity }}x {{ $item->offering?->name ?? __('Ítem') }}</span>
                                        <span class="font-medium text-text-secondary">${{ number_format((float) $item->unit_price * $item->quantity, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Action Toolbar --}}
                    <div class="pt-2 flex flex-wrap gap-2">
                        @if ($this->spotlightTicket->isCalled())
                            <flux:button
                                variant="primary"
                                icon="play"
                                wire:click="startService"
                                class="cursor-pointer min-h-11 flex-1"
                                data-test="start-service-btn"
                            >
                                {{ __('Iniciar Atención') }}
                            </flux:button>

                            <flux:button
                                variant="outline"
                                icon="bell"
                                wire:click="recall"
                                class="cursor-pointer min-h-11"
                                data-test="recall-btn"
                            >
                                {{ __('Volver a llamar') }}
                            </flux:button>

                            <flux:button
                                variant="subtle"
                                icon="pause"
                                wire:click="postpone"
                                class="cursor-pointer min-h-11"
                                data-test="postpone-btn"
                            >
                                {{ __('Posponer') }}
                            </flux:button>

                            <flux:button
                                variant="danger"
                                icon="user-minus"
                                wire:click="promptNoShow({{ $this->spotlightTicket->id }})"
                                class="cursor-pointer min-h-11"
                                data-test="no-show-btn"
                            >
                                {{ __('No se presentó') }}
                            </flux:button>
                        @elseif ($this->spotlightTicket->isInService())
                            <flux:button
                                variant="primary"
                                color="emerald"
                                icon="check-circle"
                                wire:click="complete"
                                class="cursor-pointer min-h-11 flex-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                                data-test="complete-service-btn"
                            >
                                {{ __('Completar Atención') }}
                            </flux:button>

                            <flux:button
                                variant="subtle"
                                icon="pause"
                                wire:click="postpone"
                                class="cursor-pointer min-h-11"
                                data-test="postpone-btn"
                            >
                                {{ __('Posponer') }}
                            </flux:button>

                            <flux:button
                                variant="danger"
                                icon="x-mark"
                                wire:click="promptCancel({{ $this->spotlightTicket->id }})"
                                class="cursor-pointer min-h-11"
                                data-test="cancel-btn"
                            >
                                {{ __('Cancelar') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @else
                {{-- Idle Empty State Card --}}
                <div class="rounded-2xl border-2 border-dashed border-border-strong bg-surface-card p-8 sm:p-12 text-center space-y-4" data-test="spotlight-idle">
                    <flux:icon.megaphone class="size-12 mx-auto text-text-secondary" />
                    <div>
                        <flux:heading size="lg" class="font-bold text-text-primary">
                            {{ __('Mostrador disponible') }}
                        </flux:heading>
                        <flux:subheading class="mt-1 text-sm text-text-secondary">
                            {{ __('No hay ningún cliente en atención en este momento. Llama al siguiente en la fila cuando estés listo.') }}
                        </flux:subheading>
                    </div>

                    <div class="pt-2">
                        <flux:button
                            variant="primary"
                            icon="megaphone"
                            wire:click="callNext"
                            :disabled="$this->waitingTickets->isEmpty()"
                            class="cursor-pointer w-full sm:w-auto shadow-md min-h-11"
                            data-test="call-next-btn"
                        >
                            {{ $this->waitingTickets->isEmpty() ? __('No hay clientes en espera') : __('Llamar Siguiente') }}
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Queue Tabs & List Column (Right 7-col on desktop) --}}
        <div class="space-y-6 lg:col-span-7">
            {{-- Search Bar --}}
            <div>
                <flux:input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar por turno o cliente...') }}"
                    icon="magnifying-glass"
                    clearable
                    data-test="queue-search-input"
                />
            </div>

            {{-- Tabs Navigation --}}
            <div class="flex items-center gap-1.5 sm:gap-2 border-b border-border-subtle pb-2.5 sm:pb-3 overflow-x-auto scrollbar-none max-w-full">
                <button
                    type="button"
                    wire:click="$set('activeTab', 'waiting')"
                    class="inline-flex shrink-0 items-center gap-1.5 sm:gap-2 rounded-lg px-2.5 sm:px-3.5 py-1.5 sm:py-2 text-xs sm:text-sm font-medium transition-colors cursor-pointer {{ $activeTab === 'waiting' ? 'bg-surface-subtle text-text-primary font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-subtle/50' }}"
                    data-test="tab-waiting"
                >
                    <span>{{ __('En Espera') }}</span>
                    <flux:badge size="sm" :color="$activeTab === 'waiting' ? 'indigo' : 'zinc'">
                        {{ $this->waitingTickets->count() }}
                    </flux:badge>
                </button>

                <button
                    type="button"
                    wire:click="$set('activeTab', 'postponed')"
                    class="inline-flex shrink-0 items-center gap-1.5 sm:gap-2 rounded-lg px-2.5 sm:px-3.5 py-1.5 sm:py-2 text-xs sm:text-sm font-medium transition-colors cursor-pointer {{ $activeTab === 'postponed' ? 'bg-surface-subtle text-text-primary font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-subtle/50' }}"
                    data-test="tab-postponed"
                >
                    <span>{{ __('Pospuestos') }}</span>
                    <flux:badge size="sm" :color="$activeTab === 'postponed' ? 'indigo' : 'zinc'">
                        {{ $this->postponedTickets->count() }}
                    </flux:badge>
                </button>

                <button
                    type="button"
                    wire:click="$set('activeTab', 'history')"
                    class="inline-flex shrink-0 items-center gap-1.5 sm:gap-2 rounded-lg px-2.5 sm:px-3.5 py-1.5 sm:py-2 text-xs sm:text-sm font-medium transition-colors cursor-pointer {{ $activeTab === 'history' ? 'bg-surface-subtle text-text-primary font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-subtle/50' }}"
                    data-test="tab-history"
                >
                    <span>{{ __('Historial') }}<span class="hidden sm:inline"> {{ __('de Hoy') }}</span></span>
                    <flux:badge size="sm" :color="$activeTab === 'history' ? 'indigo' : 'zinc'">
                        {{ $this->finishedTickets->count() }}
                    </flux:badge>
                </button>
            </div>

            {{-- Tab 1: En Espera --}}
            @if ($activeTab === 'waiting')
                <div class="space-y-3" data-test="tab-panel-waiting">
                    @forelse ($this->waitingTickets as $index => $ticket)
                        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 transition-colors hover:border-border-strong" data-test="waiting-ticket-{{ $ticket->id }}">
                            <div class="flex items-start gap-3">
                                <flux:badge size="sm" color="zinc" class="mt-0.5 shrink-0">
                                    #{{ $index + 1 }}
                                </flux:badge>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-text-primary text-base">{{ $ticket->ticket_number }}</span>
                                        <span class="font-medium text-text-primary">{{ $ticket->customer?->fullName() ?? __('Cliente General') }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-secondary">
                                        <span class="flex items-center gap-1">
                                            <flux:icon.clock class="size-3.5 inline text-text-secondary" />
                                            {{ __('Espera: :time', ['time' => $ticket->created_at->diffForHumans(null, true)]) }}
                                        </span>
                                        @if ($ticket->customer?->phone_number)
                                            <span>{{ $ticket->customer->phone_number }}</span>
                                        @endif
                                        <span>
                                            {{ $ticket->items->count() }} {{ trans_choice('ítem|ítems', $ticket->items->count()) }} • ${{ number_format((float) $ticket->total_price, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    icon="megaphone"
                                    wire:click="callSpecific({{ $ticket->id }})"
                                    class="cursor-pointer min-h-10"
                                    data-test="call-ticket-{{ $ticket->id }}"
                                >
                                    {{ __('Llamar') }}
                                </flux:button>

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="promptCancel({{ $ticket->id }})"
                                    class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 min-h-10"
                                    data-test="cancel-ticket-{{ $ticket->id }}"
                                >
                                    {{ __('Cancelar') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-border-strong bg-surface-card p-8 text-center space-y-2">
                            <flux:icon.queue-list class="size-10 mx-auto text-text-secondary" />
                            <flux:heading size="base" class="font-semibold text-text-primary">
                                {{ __('No hay clientes en espera en este momento') }}
                            </flux:heading>
                            <flux:subheading class="text-sm text-text-secondary">
                                {{ __('Los nuevos turnos solicitados aparecerán aquí automáticamente.') }}
                            </flux:subheading>
                        </div>
                    @endforelse
                </div>
            @endif

            {{-- Tab 2: Pospuestos --}}
            @if ($activeTab === 'postponed')
                <div class="space-y-3" data-test="tab-panel-postponed">
                    @forelse ($this->postponedTickets as $ticket)
                        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 transition-colors hover:border-border-strong" data-test="postponed-ticket-{{ $ticket->id }}">
                            <div class="flex items-start gap-3">
                                <flux:badge size="sm" color="amber" class="mt-0.5 shrink-0">
                                    {{ __('Pospuesto') }}
                                </flux:badge>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-text-primary text-base">{{ $ticket->ticket_number }}</span>
                                        <span class="font-medium text-text-primary">{{ $ticket->customer?->fullName() ?? __('Cliente General') }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-secondary">
                                        @if ($ticket->customer?->phone_number)
                                            <span>{{ $ticket->customer->phone_number }}</span>
                                        @endif
                                        <span>
                                            {{ $ticket->items->count() }} {{ trans_choice('ítem|ítems', $ticket->items->count()) }} • ${{ number_format((float) $ticket->total_price, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    icon="arrow-path"
                                    wire:click="resume({{ $ticket->id }})"
                                    class="cursor-pointer min-h-10"
                                    data-test="resume-ticket-{{ $ticket->id }}"
                                >
                                    {{ __('Reanudar') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-border-strong bg-surface-card p-8 text-center space-y-2">
                            <flux:icon.pause class="size-10 mx-auto text-text-secondary" />
                            <flux:heading size="base" class="font-semibold text-text-primary">
                                {{ __('No hay turnos pospuestos') }}
                            </flux:heading>
                            <flux:subheading class="text-sm text-text-secondary">
                                {{ __('Los turnos que decidas poner en espera temporalmente aparecerán en esta lista.') }}
                            </flux:subheading>
                        </div>
                    @endforelse
                </div>
            @endif

            {{-- Tab 3: Historial de Hoy --}}
            @if ($activeTab === 'history')
                <div class="space-y-3" data-test="tab-panel-history">
                    @forelse ($this->finishedTickets as $ticket)
                        <div class="rounded-xl border border-border-subtle bg-surface-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 transition-colors hover:border-border-strong" data-test="history-ticket-{{ $ticket->id }}">
                            <div class="flex items-start gap-3">
                                @if ($ticket->status === QueueTicketStatus::Completed)
                                    <flux:badge color="emerald" size="sm" class="mt-0.5 shrink-0">
                                        {{ __('Completado') }}
                                    </flux:badge>
                                @elseif ($ticket->status === QueueTicketStatus::NoShow)
                                    <flux:badge color="amber" size="sm" class="mt-0.5 shrink-0">
                                        {{ __('No se presentó') }}
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="mt-0.5 shrink-0">
                                        {{ __('Cancelado') }}
                                    </flux:badge>
                                @endif

                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-text-primary text-base">{{ $ticket->ticket_number }}</span>
                                        <span class="font-medium text-text-primary">{{ $ticket->customer?->fullName() ?? __('Cliente General') }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-secondary">
                                        <span>
                                            {{ __('Hora:') }} {{ ($ticket->completed_at ?? $ticket->cancelled_at ?? $ticket->updated_at)?->format('h:i A') }}
                                        </span>
                                        <span>
                                            {{ $ticket->items->count() }} {{ trans_choice('ítem|ítems', $ticket->items->count()) }} • ${{ number_format((float) $ticket->total_price, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="font-semibold text-text-primary shrink-0 self-end sm:self-center">
                                ${{ number_format((float) $ticket->total_price, 2) }}
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-border-strong bg-surface-card p-8 text-center space-y-2">
                            <flux:icon.clock class="size-10 mx-auto text-text-secondary" />
                            <flux:heading size="base" class="font-semibold text-text-primary">
                                {{ __('No hay turnos finalizados hoy') }}
                            </flux:heading>
                            <flux:subheading class="text-sm text-text-secondary">
                                {{ __('El historial de turnos completados, no asistidos o cancelados hoy se mostrará aquí.') }}
                            </flux:subheading>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    {{-- Modal 1: Confirm Call Next / Specific with Occupied Spotlight --}}
    <flux:modal name="confirm-call-next-modal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Llamar a un nuevo cliente?') }}</flux:heading>
                <flux:subheading class="mt-2 text-text-secondary">
                    {{ __('Actualmente hay un turno en el mostrador. Si llamas a otro cliente, el turno actual dejará de estar en atención. ¿Deseas continuar?') }}
                </flux:subheading>
            </div>

            @if ($this->spotlightTicket)
                <div class="rounded-lg border border-border-subtle bg-surface-subtle p-3 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-text-secondary">{{ __('Turno en mostrador actual:') }}</span>
                        <p class="font-mono font-bold text-text-primary">{{ $this->spotlightTicket->ticket_number }} — {{ $this->spotlightTicket->customer?->fullName() ?? __('Cliente') }}</p>
                    </div>
                    <flux:badge size="sm" :color="$this->spotlightTicket->isInService() ? 'emerald' : 'amber'">
                        {{ $this->spotlightTicket->isInService() ? __('En atención') : __('Llamado') }}
                    </flux:badge>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" class="cursor-pointer">
                        {{ __('Volver al mostrador') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="primary"
                    wire:click="confirmCallNext"
                    wire:loading.attr="disabled"
                    class="cursor-pointer"
                    data-test="confirm-call-next-btn"
                >
                    <span wire:loading.remove wire:target="confirmCallNext">
                        {{ __('Continuar y Llamar') }}
                    </span>
                    <span wire:loading wire:target="confirmCallNext" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Llamando...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal 2: Confirm No-Show --}}
    <flux:modal name="confirm-no-show-modal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-amber-600 dark:text-amber-400">
                    {{ __('¿Marcar cliente como "No se presentó"?') }}
                </flux:heading>
                <flux:subheading class="mt-2 text-text-secondary">
                    {{ __('El turno se registrará como no asistido y se liberará el mostrador para atender al siguiente en la fila.') }}
                </flux:subheading>
            </div>

            @if ($this->ticketForNoShow)
                <div class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 text-amber-700 dark:text-amber-400 flex items-center justify-between">
                    <div>
                        <span class="font-mono font-bold text-sm">{{ $this->ticketForNoShow->ticket_number }}</span>
                        <p class="text-xs">{{ $this->ticketForNoShow->customer?->fullName() ?? __('Cliente') }}</p>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" class="cursor-pointer">
                        {{ __('Cancelar') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="confirmNoShow"
                    wire:loading.attr="disabled"
                    class="cursor-pointer"
                    data-test="confirm-no-show-btn"
                >
                    <span wire:loading.remove wire:target="confirmNoShow">
                        {{ __('Confirmar No Asistencia') }}
                    </span>
                    <span wire:loading wire:target="confirmNoShow" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Procesando...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal 3: Confirm Cancellation --}}
    <flux:modal name="confirm-cancel-modal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-red-600 dark:text-red-400">
                    {{ __('¿Cancelar este turno?') }}
                </flux:heading>
                <flux:subheading class="mt-2 text-text-secondary">
                    {{ __('Esta acción cancelará el turno de la fila virtual de forma definitiva.') }}
                </flux:subheading>
            </div>

            @if ($this->ticketToCancel)
                <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-red-700 dark:text-red-400 flex items-center justify-between">
                    <div>
                        <span class="font-mono font-bold text-sm">{{ $this->ticketToCancel->ticket_number }}</span>
                        <p class="text-xs">{{ $this->ticketToCancel->customer?->fullName() ?? __('Cliente') }}</p>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" class="cursor-pointer">
                        {{ __('Volver') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="confirmCancel"
                    wire:loading.attr="disabled"
                    class="cursor-pointer"
                    data-test="confirm-cancel-btn"
                >
                    <span wire:loading.remove wire:target="confirmCancel">
                        {{ __('Confirmar Cancelación') }}
                    </span>
                    <span wire:loading wire:target="confirmCancel" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Cancelando...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
