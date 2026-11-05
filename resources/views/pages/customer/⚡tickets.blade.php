<?php

declare(strict_types=1);

use App\Enums\QueueTicketStatus;
use App\Models\Customer;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mis Turnos')] #[Layout('layouts.customer')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'active';

    /**
     * @var list<int>
     */
    public array $acknowledgedTickets = [];

    /**
     * @var list<int>
     */
    public array $trackedTicketIds = [];

    public function mount(?QueueService $queueService = null): void
    {
        $queueService ??= app(QueueService::class);
        $active = $queueService->getActiveTicketsForCustomer($this->customer());
        $this->trackedTicketIds = $active->pluck('id')->all();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['active', 'history'], true) ? $tab : 'active';
    }

    public function acknowledgeTicket(int $ticketId): void
    {
        if (! in_array($ticketId, $this->acknowledgedTickets, true)) {
            $this->acknowledgedTickets[] = $ticketId;
        }
    }

    public function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }

    /**
     * @return Collection<int, QueueTicket>
     */
    #[Computed]
    public function activeTickets(?QueueService $queueService = null): Collection
    {
        $queueService ??= app(QueueService::class);
        $active = $queueService->getActiveTicketsForCustomer($this->customer());

        foreach ($active as $ticket) {
            if (! in_array($ticket->id, $this->trackedTicketIds, true)) {
                $this->trackedTicketIds[] = $ticket->id;
            }
        }

        $missingIds = array_values(array_diff($this->trackedTicketIds, $active->pluck('id')->all(), $this->acknowledgedTickets));

        if ($missingIds !== []) {
            /** @var Collection<int, QueueTicket> $terminalTickets */
            $terminalTickets = QueueTicket::query()
                ->whereIn('id', $missingIds)
                ->where('customer_id', $this->customer()->id)
                ->with([
                    'business',
                    'items.offering' => function ($query): void {
                        $query->withTrashed();
                    },
                ])
                ->get();

            $active = new Collection([...$active, ...$terminalTickets]);
        }

        if ($this->acknowledgedTickets !== []) {
            $active = $active->reject(fn (QueueTicket $ticket): bool => in_array($ticket->id, $this->acknowledgedTickets, true))->values();
        }

        return new Collection($active->all());
    }

    /**
     * @return LengthAwarePaginator<int, QueueTicket>
     */
    #[Computed]
    public function historyTickets(?QueueService $queueService = null): LengthAwarePaginator
    {
        $queueService ??= app(QueueService::class);

        return $queueService->getHistoricalTicketsForCustomer($this->customer(), 10);
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 space-y-6">
    {{-- Header & Tab Controls --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-text-primary sm:text-3xl">
                Mis Turnos
            </h1>
            <p class="text-xs sm:text-sm text-text-secondary mt-1">
                Monitorea tus filas virtuales y revisa tu historial de atención.
            </p>
        </div>

        {{-- Tab Selector Pills --}}
        <div class="inline-flex rounded-xl bg-surface-subtle p-1 border border-border-subtle self-start sm:self-auto" role="tablist">
            <button
                type="button"
                wire:click="switchTab('active')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $tab === 'active' ? 'bg-surface-card text-text-primary shadow-xs border border-border-subtle' : 'text-text-secondary hover:text-text-primary' }}"
                role="tab"
                aria-selected="{{ $tab === 'active' ? 'true' : 'false' }}"
                data-test="tab-active-turns"
            >
                <span>Turnos Activos</span>
                @if ($this->activeTickets->count() > 0)
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold rounded-full {{ $tab === 'active' ? 'bg-brand-primary text-white' : 'bg-surface-card text-text-secondary border border-border-subtle' }}">
                        {{ $this->activeTickets->count() }}
                    </span>
                @endif
            </button>

            <button
                type="button"
                wire:click="switchTab('history')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $tab === 'history' ? 'bg-surface-card text-text-primary shadow-xs border border-border-subtle' : 'text-text-secondary hover:text-text-primary' }}"
                role="tab"
                aria-selected="{{ $tab === 'history' ? 'true' : 'false' }}"
                data-test="tab-history"
            >
                <span>Historial</span>
            </button>
        </div>
    </div>

    {{-- Tab 1: Active Turns --}}
    @if ($tab === 'active')
        <div wire:poll.5s class="space-y-4" data-test="active-tickets-container">
            @forelse ($this->activeTickets as $ticket)
                <div class="rounded-2xl bg-surface-card border border-border-subtle p-5 sm:p-6 shadow-xs space-y-4" data-test="ticket-card-{{ $ticket->id }}">
                    {{-- Urgency Alerts --}}
                    @if ($ticket->isTerminal())
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                <flux:icon.check-circle class="size-5 shrink-0" />
                                <span>Tu turno ha finalizado con éxito.</span>
                            </div>
                            <flux:button size="sm" variant="subtle" wire:click="acknowledgeTicket({{ $ticket->id }})" class="cursor-pointer shrink-0">
                                Entendido
                            </flux:button>
                        </div>
                    @elseif ($ticket->status === QueueTicketStatus::Called)
                        <div class="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-4 flex items-center gap-3">
                            <span class="relative flex size-3 shrink-0">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full size-3 bg-indigo-500"></span>
                            </span>
                            <div class="flex-1 text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                ¡Es tu turno! Acércate al módulo de atención
                            </div>
                        </div>
                    @elseif ($ticket->status === QueueTicketStatus::Ready)
                        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex items-center gap-3 animate-pulse">
                            <flux:icon.check-circle class="size-5 text-emerald-500 shrink-0" />
                            <div class="flex-1 text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                ¡Tu pedido está listo para recoger en el mostrador!
                            </div>
                        </div>
                    @elseif ($ticket->status === QueueTicketStatus::Skipped)
                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex items-center gap-3">
                            <flux:icon.exclamation-triangle class="size-5 text-amber-500 shrink-0" />
                            <div class="flex-1 text-sm font-medium text-amber-600 dark:text-amber-400">
                                Tu turno fue pospuesto. Por favor preséntate ante el personal del establecimiento
                            </div>
                        </div>
                    @endif

                    {{-- Card Header: Venue Info & Status Badge --}}
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            @if ($ticket->business?->logo_path)
                                <img
                                    src="{{ Storage::url($ticket->business->logo_path) }}"
                                    alt="{{ $ticket->business->business_name }}"
                                    class="size-11 rounded-xl object-cover border border-border-subtle bg-surface-subtle shrink-0"
                                />
                            @else
                                <div class="flex size-11 items-center justify-center rounded-xl bg-surface-subtle text-text-primary font-bold text-sm border border-border-subtle shrink-0">
                                    {{ $ticket->business?->initials() ?? 'TX' }}
                                </div>
                            @endif

                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-text-primary truncate">
                                    {{ $ticket->business?->business_name ?? 'Establecimiento' }}
                                </h3>
                                <p class="text-xs text-text-secondary">
                                    Solicitado {{ $ticket->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </div>

                        <flux:badge :color="$ticket->status->color()" size="sm">
                            {{ $ticket->status->label() }}
                        </flux:badge>
                    </div>

                    {{-- Card Core: Ticket Number, Queue Position & Total --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-border-subtle pt-3">
                        <div>
                            <span class="block text-xs uppercase tracking-wider font-semibold text-text-secondary">
                                Número de turno
                            </span>
                            <span class="text-3xl sm:text-4xl font-black text-text-primary font-mono tracking-tight">
                                {{ $ticket->ticket_number }}
                            </span>
                        </div>

                        <div class="flex items-center gap-6 sm:text-right">
                            @if (! $ticket->isTerminal())
                                <div>
                                    <span class="block text-xs uppercase tracking-wider font-semibold text-text-secondary">
                                        Posición en fila
                                    </span>
                                    @if ($ticket->status === QueueTicketStatus::Pending)
                                        @if ($ticket->turns_ahead === 0)
                                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                                ¡Eres el siguiente!
                                            </span>
                                        @elseif ($ticket->turns_ahead === 1)
                                            <span class="text-sm font-semibold text-text-primary">
                                                1 persona antes de ti
                                            </span>
                                        @else
                                            <span class="text-sm font-semibold text-text-primary">
                                                {{ $ticket->turns_ahead }} personas antes de ti
                                            </span>
                                        @endif
                                    @elseif ($ticket->status === QueueTicketStatus::Preparing)
                                        <span class="text-sm font-semibold text-sky-600 dark:text-sky-400">
                                            En preparación
                                        </span>
                                    @elseif ($ticket->status === QueueTicketStatus::Called)
                                        <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                            ¡Llamado a módulo!
                                        </span>
                                    @elseif ($ticket->status === QueueTicketStatus::Ready)
                                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                            Listo en mostrador
                                        </span>
                                    @elseif ($ticket->status === QueueTicketStatus::InService)
                                        <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                            En atención
                                        </span>
                                    @elseif ($ticket->status === QueueTicketStatus::Skipped)
                                        <span class="text-sm font-semibold text-amber-600 dark:text-amber-400">
                                            Pospuesto
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div>
                                <span class="block text-xs uppercase tracking-wider font-semibold text-text-secondary">
                                    Total orden
                                </span>
                                <span class="text-lg sm:text-xl font-extrabold text-text-primary">
                                    $ {{ number_format((float) $ticket->total_price, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Alpine.js Collapsible Accordion for Order Breakdown --}}
                    <div x-data="{ expanded: false }" class="border-t border-border-subtle pt-3">
                        <button
                            type="button"
                            @click="expanded = !expanded"
                            class="flex items-center justify-between w-full text-xs font-semibold text-text-secondary hover:text-text-primary transition-colors cursor-pointer"
                        >
                            <span x-text="expanded ? 'Ocultar detalle' : 'Ver detalle'">Ver detalle</span>
                            <flux:icon.chevron-down class="size-4 transition-transform duration-200" ::class="{ 'rotate-180': expanded }" />
                        </button>

                        <div
                            x-show="expanded"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="mt-3 space-y-3 pt-3 border-t border-border-subtle"
                        >
                            <div class="divide-y divide-border-subtle text-xs">
                                @foreach ($ticket->items as $item)
                                    <div class="flex items-center justify-between py-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="inline-flex size-5 items-center justify-center rounded-md bg-surface-subtle text-xs font-bold text-text-primary border border-border-subtle shrink-0">
                                                {{ $item->quantity }}x
                                            </span>
                                            <span class="font-medium text-text-primary truncate">
                                                {{ $item->offering?->name ?? 'Producto' }}
                                            </span>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-text-secondary">
                                                $ {{ number_format((float) $item->unit_price, 0, ',', '.') }} c/u
                                            </span>
                                            <span class="font-semibold text-text-primary ml-2">
                                                $ {{ number_format((float) $item->subtotal(), 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-between pt-2 text-xs text-text-secondary border-t border-border-subtle">
                                <span>Hora de solicitud:</span>
                                <span>{{ $ticket->created_at?->format('h:i A') }}</span>
                            </div>

                            <div class="pt-2 flex justify-end">
                                <flux:button href="{{ route('tickets.show', $ticket->ticket_code) }}" variant="subtle" size="sm">
                                    Ver en pantalla completa
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-surface-card border border-border-subtle p-8 sm:p-12 text-center space-y-4" data-test="empty-active-state">
                    <flux:icon.ticket class="size-12 text-text-secondary/60 mx-auto" />
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-text-primary">No tienes turnos activos en este momento</h3>
                        <p class="text-xs text-text-secondary max-w-sm mx-auto">
                            Cuando solicites un turno en cualquier local o establecimiento, podrás seguirlo aquí en tiempo real.
                        </p>
                    </div>
                    <div class="pt-2">
                        <flux:button href="{{ url('/explore') }}" variant="primary" size="sm">
                            Explorar Locales
                        </flux:button>
                    </div>
                </div>
            @endforelse
        </div>
    @endif

    {{-- Tab 2: History --}}
    @if ($tab === 'history')
        <div class="space-y-4" data-test="history-tickets-container">
            @forelse ($this->historyTickets as $ticket)
                <div class="rounded-2xl bg-surface-card border border-border-subtle p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4" data-test="history-ticket-card-{{ $ticket->id }}">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-3">
                            <span class="font-mono font-bold text-lg text-text-primary">
                                {{ $ticket->ticket_number }}
                            </span>
                            <flux:badge :color="$ticket->status->color()" size="sm">
                                {{ $ticket->status->label() }}
                            </flux:badge>
                        </div>
                        <h4 class="text-sm font-semibold text-text-primary truncate">
                            {{ $ticket->business?->business_name ?? 'Establecimiento' }}
                        </h4>
                        <div class="flex items-center gap-3 text-xs text-text-secondary">
                            <span>{{ $ticket->created_at?->format('d/m/Y h:i A') }}</span>
                            <span>&bull;</span>
                            <span>{{ $ticket->items->sum('quantity') }} {{ $ticket->items->sum('quantity') === 1 ? 'producto' : 'productos' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-4 border-t sm:border-t-0 border-border-subtle pt-3 sm:pt-0">
                        <div class="text-left sm:text-right">
                            <span class="block text-xs text-text-secondary">Total</span>
                            <span class="font-bold text-base text-text-primary">
                                $ {{ number_format((float) $ticket->total_price, 0, ',', '.') }}
                            </span>
                        </div>

                        <flux:button href="{{ route('tickets.show', $ticket->ticket_code) }}" variant="subtle" size="sm" icon="eye">
                            Ver
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-surface-card border border-border-subtle p-8 sm:p-12 text-center space-y-4" data-test="empty-history-state">
                    <flux:icon.clock class="size-12 text-text-secondary/60 mx-auto" />
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-text-primary">No tienes visitas o turnos pasados en tu historial</h3>
                        <p class="text-xs text-text-secondary max-w-sm mx-auto">
                            Tus turnos completados o cancelados se archivarán aquí para tu referencia.
                        </p>
                    </div>
                </div>
            @endforelse

            @if ($this->historyTickets->hasPages())
                <div class="pt-4" data-test="history-pagination">
                    {{ $this->historyTickets->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
