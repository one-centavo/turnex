<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Models\Business;
use App\Services\ExploreBusinessesService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Explorar')] #[Layout('layouts.customer')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'categoria', history: true)]
    public string $category = '';

    public function mount(): void
    {
        if ($this->search === '' && request()->filled('search')) {
            $this->search = (string) request()->query('search');
        }

        if ($this->category === '' && request()->filled('category')) {
            $this->category = (string) request()->query('category');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function setCategory(string $category): void
    {
        if ($category === '') {
            $this->category = '';
        } else {
            $this->category = $this->category === $category ? '' : $category;
        }

        $this->resetPage();
    }

    public function selectCategory(string $category): void
    {
        $this->setCategory($category);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->category = '';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Business>
     */
    #[Computed]
    public function businesses(?ExploreBusinessesService $service = null): LengthAwarePaginator
    {
        $service ??= app(ExploreBusinessesService::class);

        return $service->getPaginatedBusinesses($this->search, $this->category, 12);
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function activeCustomerBusinessIds(?ExploreBusinessesService $service = null): array
    {
        $service ??= app(ExploreBusinessesService::class);
        $customerId = Auth::guard('customer')->id();

        return $service->getActiveCustomerBusinessIds($customerId !== null ? (int) $customerId : null);
    }

    /**
     * @return array{businesses: LengthAwarePaginator<int, Business>, activeCustomerBusinessIds: array<int, int>}
     */
    public function with(?ExploreBusinessesService $service = null): array
    {
        $service ??= app(ExploreBusinessesService::class);

        return [
            'businesses' => $this->businesses($service),
            'activeCustomerBusinessIds' => $this->activeCustomerBusinessIds($service),
        ];
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
    {{-- Hero Section --}}
    <div class="space-y-3">
        <h1 class="text-3xl font-bold tracking-tight text-text-primary sm:text-4xl">
            Explora Establecimientos
        </h1>
        <p class="text-sm text-text-secondary sm:text-base max-w-2xl leading-relaxed">
            Descubre comercios verificados, consulta el estado de las filas en tiempo real y pide tu turno.
        </p>
    </div>

    {{-- Search & Category Filter Controls --}}
    <div class="space-y-4">
        {{-- Search Input --}}
        <div class="relative w-full max-w-xl">
            <flux:input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nombre de negocio o dirección..."
                icon="magnifying-glass"
                clearable
                data-test="explore-search-input"
            />
        </div>

        {{-- Category Filter Chips --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none sm:flex-wrap" data-test="category-filter-chips">
            <button
                type="button"
                wire:click="setCategory('')"
                @class([
                    'inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-medium transition-colors cursor-pointer shrink-0',
                    'bg-brand-primary text-white border-brand-primary font-semibold shadow-xs' => $category === '',
                    'bg-surface-card text-text-secondary border-border-subtle hover:text-text-primary hover:border-border-strong' => $category !== '',
                ])
                data-test="category-chip-all"
            >
                Todos
            </button>

            @foreach (BusinessCategory::cases() as $cat)
                <button
                    type="button"
                    wire:click="setCategory('{{ $cat->value }}')"
                    @class([
                        'inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-medium transition-colors cursor-pointer shrink-0',
                        'bg-brand-primary text-white border-brand-primary font-semibold shadow-xs' => $category === $cat->value,
                        'bg-surface-card text-text-secondary border-border-subtle hover:text-text-primary hover:border-border-strong' => $category !== $cat->value,
                    ])
                    data-test="category-chip-{{ $cat->value }}"
                >
                    {{ $cat->label() }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Businesses Grid / Empty State --}}
    @if ($businesses->isEmpty())
        <div class="rounded-xl border border-border-subtle bg-surface-card p-8 sm:p-12 text-center" data-test="explore-empty-state">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-subtle text-text-secondary">
                <flux:icon.magnifying-glass class="size-6 text-text-secondary" />
            </div>
            <h3 class="mt-4 text-base font-semibold text-text-primary">
                No se encontraron negocios
            </h3>
            <p class="mt-2 text-sm text-text-secondary max-w-md mx-auto">
                Intenta ajustar tu búsqueda o limpiar los filtros seleccionados.
            </p>
            <div class="mt-6">
                <flux:button
                    wire:click="resetFilters"
                    variant="outline"
                    icon="arrow-path"
                    class="cursor-pointer"
                    data-test="explore-reset-filters-button"
                >
                    Limpiar filtros
                </flux:button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" data-test="explore-businesses-grid">
            @foreach ($businesses as $business)
                <div
                    wire:key="business-{{ $business->id }}"
                    class="group relative flex flex-col rounded-xl border border-border-subtle bg-surface-card overflow-hidden transition-all hover:border-border-strong hover:shadow-md"
                    data-test="business-card-{{ $business->id }}"
                >
                    {{-- Clickable card overlay link --}}
                    <a
                        href="{{ route('storefront.show', ['slug' => $business->slug]) }}"
                        wire:navigate
                        class="absolute inset-0 z-10"
                        aria-label="{{ $business->business_name }}"
                    ></a>

                    {{-- Media banner container --}}
                    <div class="relative h-36 w-full overflow-hidden bg-surface-subtle border-b border-border-subtle">
                        @if ($business->cover_photo_path)
                            <img
                                src="{{ Storage::url($business->cover_photo_path) }}"
                                alt="{{ $business->business_name }}"
                                loading="lazy"
                                class="h-full w-full object-cover rounded-t-xl"
                            />
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-linear-to-br from-surface-subtle via-surface-card to-blue-900/20">
                                <flux:icon.building-storefront class="size-12 text-border-strong" />
                            </div>
                        @endif

                        {{-- Category badge --}}
                        <div class="absolute top-3 left-3 z-20">
                            <flux:badge variant="subtle" size="sm">
                                {{ $business->business_category->label() }}
                            </flux:badge>
                        </div>

                        {{-- Active turn indicator --}}
                        @if (isset($activeCustomerBusinessIds[$business->id]))
                            <div class="absolute top-3 right-3 z-20" data-test="active-turn-badge-{{ $business->id }}">
                                <flux:badge color="blue" icon="ticket" size="sm" class="font-semibold shadow-xs">
                                    Turno activo
                                </flux:badge>
                            </div>
                        @endif
                    </div>

                    {{-- Card body --}}
                    <div class="flex flex-1 flex-col justify-between p-5">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                @if ($business->logo_path)
                                    <img
                                        src="{{ Storage::url($business->logo_path) }}"
                                        alt="{{ $business->business_name }}"
                                        class="size-10 rounded-lg object-cover border border-border-subtle shrink-0"
                                    />
                                @else
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-subtle border border-border-subtle font-bold text-sm text-text-primary">
                                        {{ $business->initials() }}
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <flux:heading size="lg" class="truncate font-semibold text-text-primary" title="{{ $business->business_name }}">
                                        {{ $business->business_name }}
                                    </flux:heading>

                                    @if ($business->location)
                                        <div class="mt-0.5 flex items-center gap-1 text-xs text-text-secondary truncate">
                                            <flux:icon.map-pin class="size-3.5 shrink-0 text-text-secondary/70" />
                                            <span class="truncate">{{ $business->location }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div>
                                @if ((int) $business->active_tickets_count === 0)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">
                                        Sin fila
                                    </flux:badge>
                                @elseif ((int) $business->active_tickets_count === 1)
                                    <flux:badge color="amber" size="sm" icon="clock">
                                        1 persona en espera
                                    </flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm" icon="clock">
                                        {{ $business->active_tickets_count }} personas en espera
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        {{-- Storefront CTA --}}
                        <div class="mt-5 pt-2">
                            <flux:button
                                href="{{ route('storefront.show', ['slug' => $business->slug]) }}"
                                wire:navigate
                                variant="primary"
                                class="relative z-20 w-full cursor-pointer"
                                data-test="storefront-cta-{{ $business->id }}"
                            >
                                Ver Catálogo y Turnos
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="pt-6" data-test="explore-pagination">
            <flux:pagination :paginator="$businesses" />
        </div>
    @endif
</div>
