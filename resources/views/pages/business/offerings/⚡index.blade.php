<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Services\OfferingService;
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

new #[Title('Productos y Servicios')] #[Layout('layouts.business')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $sortField = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'status']);
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    /**
     * Get active non-archived categories for filter dropdown.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::active()->orderBy('name')->get();
    }

    /**
     * Total number of offerings created by this business tenant.
     */
    #[Computed]
    public function totalOfferingsCount(): int
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        return $business?->offerings()->count() ?? 0;
    }

    /**
     * Get paginated offerings strictly scoped to authenticated business tenant.
     *
     * @return LengthAwarePaginator<Offering>
     */
    #[Computed]
    public function offerings(): LengthAwarePaginator
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            throw new RuntimeException('Authenticated business is required to view offerings.');
        }

        return app(OfferingService::class)->listForBusiness(
            business: $business,
            filters: [
                'search' => $this->search,
                'category' => $this->category,
                'status' => $this->status,
            ],
            sortField: $this->sortField,
            sortDirection: $this->sortDirection,
            perPage: 10,
        );
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-400" role="alert">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <span class="text-sm font-medium">{{ session('status') }}</span>
            </div>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">
                {{ __('Productos y Servicios') }}
            </h1>
            <p class="mt-1 text-sm text-text-secondary">
                {{ __('Gestiona el catálogo comercial de productos y servicios que ofreces a tus clientes.') }}
            </p>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                :href="route('business.offerings.create')"
                wire:navigate
                class="cursor-pointer"
                data-test="create-offering-button"
            >
                {{ __('Nuevo Producto o Servicio') }}
            </flux:button>
        </div>
    </div>

    @if ($this->totalOfferingsCount === 0)
        <div class="rounded-xl border border-border-subtle bg-surface-card p-8 sm:p-12 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-subtle text-text-secondary">
                <flux:icon.shopping-bag class="size-6 text-brand-primary" />
            </div>
            <h3 class="mt-4 text-base font-semibold text-text-primary">
                {{ __('Catálogo comercial') }}
            </h3>
            <p class="mt-2 text-sm text-text-secondary max-w-md mx-auto">
                {{ __('Registra tus servicios y productos comerciales para que los clientes puedan descubrirlos y seleccionarlos al unirse a tus turnos virtuales.') }}
            </p>
            <div class="mt-6">
                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('business.offerings.create')"
                    wire:navigate
                    class="cursor-pointer"
                >
                    {{ __('Crear mi primer servicio') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="flex flex-col sm:flex-row gap-4 sm:items-center">
            <div class="flex-1 sm:max-w-xs">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar por nombre...') }}"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <flux:select wire:model.live="category">
                    <flux:select.option value="">{{ __('Todas las categorías') }}</flux:select.option>
                    @foreach ($this->categories as $categoryOption)
                        <flux:select.option :value="$categoryOption->id">
                            {{ $categoryOption->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-full sm:w-44">
                <flux:select wire:model.live="status">
                    <flux:select.option value="all">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Activos') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactivos') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->offerings->isEmpty())
            <div class="rounded-xl border border-border-subtle bg-surface-card p-8 sm:p-12 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-subtle text-text-secondary">
                    <flux:icon.magnifying-glass class="size-6 text-text-secondary" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-text-primary">
                    {{ __('No se encontraron resultados') }}
                </h3>
                <p class="mt-2 text-sm text-text-secondary max-w-md mx-auto">
                    {{ __('No se encontraron productos o servicios que coincidan con tus criterios.') }}
                </p>
                <div class="mt-6">
                    <flux:button
                        variant="filled"
                        wire:click="resetFilters"
                        class="cursor-pointer"
                    >
                        {{ __('Restablecer filtros') }}
                    </flux:button>
                </div>
            </div>
        @else
            <flux:table :paginate="$this->offerings">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">
                        {{ __('Producto o Servicio') }}
                    </flux:table.column>
                    <flux:table.column>{{ __('Categoría') }}</flux:table.column>
                    <flux:table.column>{{ __('Precio') }}</flux:table.column>
                    <flux:table.column>{{ __('Duración') }}</flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortField === 'created_at'"
                        :direction="$sortDirection"
                        wire:click="sortBy('created_at')"
                    >
                        {{ __('Fecha') }}
                    </flux:table.column>
                    <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->offerings as $offering)
                        <flux:table.row :key="$offering->id">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-3">
                                    @if ($offering->reference_image)
                                        <img
                                            src="{{ Storage::url($offering->reference_image) }}"
                                            alt="{{ $offering->name }}"
                                            class="size-10 shrink-0 rounded-lg object-cover"
                                        />
                                    @else
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-subtle text-text-secondary">
                                            <flux:icon.shopping-bag class="size-5" />
                                        </div>
                                    @endif
                                    <span class="truncate font-medium text-text-primary">
                                        {{ $offering->name }}
                                    </span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $offering->category?->name ?? __('Sin categoría') }}
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ((float) $offering->price === 0.0 || $offering->price == 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-subtle text-text-secondary">
                                        {{ __('Gratis') }}
                                    </span>
                                @else
                                    <span class="font-medium text-text-primary">
                                        {{ '$' . number_format((float) $offering->price, 2) }}
                                    </span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $offering->estimated_duration_minutes ? $offering->estimated_duration_minutes . ' min' : '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($offering->is_active)
                                    <span class="text-sm font-medium text-text-secondary">{{ __('Activo') }}</span>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactivo') }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-text-secondary whitespace-nowrap">
                                {{ $offering->created_at->format('d/m/Y') }}
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('business.offerings.edit', $offering)"
                                    wire:navigate
                                    class="cursor-pointer"
                                    data-test="edit-offering-button-{{ $offering->id }}"
                                >
                                    {{ __('Editar') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    @endif
</div>
