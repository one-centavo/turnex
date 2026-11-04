<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Services\OfferingService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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

    public ?int $confirmingArchiveId = null;

    public ?int $confirmingRestoreId = null;

    public ?int $confirmingPermanentDeleteId = null;

    public ?string $errorMessage = null;

    public function updatedSearch(): void
    {
        $this->errorMessage = null;
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->errorMessage = null;
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->errorMessage = null;
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

        $this->errorMessage = null;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'status', 'errorMessage']);
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
     * Total number of offerings created by this business tenant (including archived).
     */
    #[Computed]
    public function totalOfferingsCount(): int
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        return $business?->offerings()->withTrashed()->count() ?? 0;
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
            throw new \RuntimeException('Authenticated business is required to view offerings.');
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

    /**
     * Offering pending archive confirmation.
     */
    #[Computed]
    public function offeringToArchive(): ?Offering
    {
        if (! $this->confirmingArchiveId) {
            return null;
        }

        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        return $business?->offerings()->find($this->confirmingArchiveId);
    }

    /**
     * Offering pending restore confirmation.
     */
    #[Computed]
    public function offeringToRestore(): ?Offering
    {
        if (! $this->confirmingRestoreId) {
            return null;
        }

        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        return $business?->offerings()->onlyTrashed()->find($this->confirmingRestoreId);
    }

    /**
     * Offering pending permanent deletion confirmation.
     */
    #[Computed]
    public function offeringToDelete(): ?Offering
    {
        if (! $this->confirmingPermanentDeleteId) {
            return null;
        }

        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        return $business?->offerings()->onlyTrashed()->find($this->confirmingPermanentDeleteId);
    }

    /**
     * Initiate offering archive confirmation modal.
     */
    public function confirmArchive(int $id): void
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401);
        }

        $offering = $business->offerings()->findOrFail($id);

        $this->errorMessage = null;
        $this->confirmingArchiveId = $offering->id;
        $this->modal('confirm-offering-archive')->show();
    }

    /**
     * Execute offering archive.
     */
    public function archive(OfferingService $offeringService): void
    {
        if (! $this->confirmingArchiveId) {
            return;
        }

        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401);
        }

        $offering = $business->offerings()->findOrFail($this->confirmingArchiveId);

        $offeringService->archive($offering);

        $this->modal('confirm-offering-archive')->close();
        $this->confirmingArchiveId = null;

        session()->flash('status', __('Service archived successfully.'));
        Flux::toast(variant: 'success', text: __('Service archived successfully.'));
    }

    /**
     * Initiate offering restore confirmation modal.
     */
    public function confirmRestore(int $id): void
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401);
        }

        $offering = $business->offerings()->onlyTrashed()->findOrFail($id);

        $this->errorMessage = null;
        $this->confirmingRestoreId = $offering->id;
        $this->modal('confirm-offering-restore')->show();
    }

    /**
     * Execute offering restoration to active catalog.
     */
    public function restore(OfferingService $offeringService): void
    {
        if (! $this->confirmingRestoreId) {
            return;
        }

        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401);
        }

        $offering = $business->offerings()->onlyTrashed()->findOrFail($this->confirmingRestoreId);

        $offeringService->restore($offering);

        $this->modal('confirm-offering-restore')->close();
        $this->confirmingRestoreId = null;

        session()->flash('status', __('Service restored successfully.'));
        Flux::toast(variant: 'success', text: __('Service restored successfully.'));
    }

    /**
     * Initiate permanent deletion confirmation or block if historical activity exists.
     */
    public function confirmPermanentDelete(int $id, OfferingService $offeringService): void
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401);
        }

        $offering = $business->offerings()->onlyTrashed()->findOrFail($id);

        if ($offeringService->hasHistoricalActivity($offering)) {
            $alert = __('This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.');
            $this->errorMessage = $alert;
            session()->flash('error', $alert);
            Flux::toast(variant: 'danger', text: $alert);

            return;
        }

        $this->errorMessage = null;
        $this->confirmingPermanentDeleteId = $offering->id;
        $this->modal('confirm-offering-permanent-delete')->show();
    }

    /**
     * Execute permanent deletion of an offering and reference image.
     */
    public function deletePermanently(OfferingService $offeringService): void
    {
        if (! $this->confirmingPermanentDeleteId) {
            return;
        }

        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            abort(401);
        }

        $offering = $business->offerings()->onlyTrashed()->findOrFail($this->confirmingPermanentDeleteId);

        try {
            $offeringService->deletePermanently($offering);

            $this->modal('confirm-offering-permanent-delete')->close();
            $this->confirmingPermanentDeleteId = null;

            session()->flash('status', __('Service permanently deleted.'));
            Flux::toast(variant: 'success', text: __('Service permanently deleted.'));
        } catch (\DomainException $e) {
            $this->modal('confirm-offering-permanent-delete')->close();
            $this->confirmingPermanentDeleteId = null;

            $alert = $e->getMessage() ?: __('This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.');
            $this->errorMessage = $alert;
            session()->flash('error', $alert);
            Flux::toast(variant: 'danger', text: $alert);
        }
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

    @if (session('error') || $errorMessage)
        <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-red-700 dark:text-red-400" role="alert">
            <div class="flex items-center gap-2">
                <flux:icon.exclamation-triangle class="size-5 shrink-0 text-red-600 dark:text-red-400" />
                <span class="text-sm font-medium">{{ session('error') ?? $errorMessage }}</span>
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

            <div class="w-full sm:w-48">
                <flux:select wire:model.live="status">
                    <flux:select.option value="all">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Activos') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactivos') }}</flux:select.option>
                    <flux:select.option value="archived">{{ __('Archivados') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->offerings->isEmpty())
            <div class="rounded-xl border border-border-subtle bg-surface-card p-8 sm:p-12 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-subtle text-text-secondary">
                    @if ($this->status === 'archived')
                        <flux:icon.archive-box class="size-6 text-text-secondary" />
                    @else
                        <flux:icon.magnifying-glass class="size-6 text-text-secondary" />
                    @endif
                </div>
                <h3 class="mt-4 text-base font-semibold text-text-primary">
                    @if ($this->status === 'archived')
                        {{ __('No se encontraron productos o servicios archivados.') }}
                    @else
                        {{ __('No se encontraron resultados') }}
                    @endif
                </h3>
                <p class="mt-2 text-sm text-text-secondary max-w-md mx-auto">
                    @if ($this->status === 'archived')
                        {{ __('Los productos o servicios que archives aparecerán aquí.') }}
                    @else
                        {{ __('No se encontraron productos o servicios que coincidan con tus criterios.') }}
                    @endif
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
                    <flux:table.column align="end">
                        {{ __('Acciones') }}
                    </flux:table.column>
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
                                @if ($offering->trashed())
                                    <flux:badge color="zinc" size="sm">{{ __('Archivado') }}</flux:badge>
                                @elseif ($offering->is_active)
                                    <span class="text-sm font-medium text-text-secondary">{{ __('Activo') }}</span>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactivo') }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-text-secondary whitespace-nowrap">
                                {{ $offering->created_at->format('d/m/Y') }}
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($offering->trashed())
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="arrow-path"
                                            wire:click="confirmRestore({{ $offering->id }})"
                                            class="cursor-pointer text-text-secondary hover:text-text-primary"
                                            data-test="restore-offering-{{ $offering->id }}"
                                        >
                                            {{ __('Restaurar') }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="confirmPermanentDelete({{ $offering->id }})"
                                            class="cursor-pointer text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            data-test="permanent-delete-offering-{{ $offering->id }}"
                                        >
                                            {{ __('Eliminar definitivamente') }}
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            :href="Route::has('business.offerings.edit') ? route('business.offerings.edit', $offering) : '#'"
                                            class="cursor-pointer text-text-secondary hover:text-text-primary"
                                            data-test="edit-offering-{{ $offering->id }}"
                                        >
                                            {{ __('Editar') }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="archive-box"
                                            wire:click="confirmArchive({{ $offering->id }})"
                                            class="cursor-pointer text-text-secondary hover:text-text-primary"
                                            data-test="archive-offering-{{ $offering->id }}"
                                        >
                                            {{ __('Archivar') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    @endif

    {{-- Confirm Archive Modal --}}
    <flux:modal name="confirm-offering-archive" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Archivar producto o servicio?') }}</flux:heading>
                <flux:subheading class="mt-2 text-text-secondary">
                    {{ __('Este producto o servicio se ocultará del catálogo comercial y de la vista de tus clientes, pero todo su historial de turnos y pedidos permanecerá intacto.') }}
                </flux:subheading>
            </div>

            @if ($this->offeringToArchive)
                <div class="rounded-lg border border-border-subtle bg-surface-subtle p-3 flex items-center gap-3">
                    <flux:icon.archive-box class="size-5 text-text-secondary shrink-0" />
                    <span class="font-medium text-text-primary text-sm truncate">
                        {{ $this->offeringToArchive->name }}
                    </span>
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
                    wire:click="archive"
                    wire:loading.attr="disabled"
                    class="cursor-pointer"
                    data-test="confirm-archive-btn"
                >
                    <span wire:loading.remove wire:target="archive">
                        {{ __('Archivar servicio') }}
                    </span>
                    <span wire:loading wire:target="archive" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Archivando...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Confirm Restore Modal --}}
    <flux:modal name="confirm-offering-restore" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Restaurar producto o servicio?') }}</flux:heading>
                <flux:subheading class="mt-2 text-text-secondary">
                    {{ __('Este producto o servicio volverá a estar disponible en tu catálogo comercial con su configuración anterior.') }}
                </flux:subheading>
            </div>

            @if ($this->offeringToRestore)
                <div class="rounded-lg border border-border-subtle bg-surface-subtle p-3 flex items-center gap-3">
                    <flux:icon.arrow-path class="size-5 text-text-secondary shrink-0" />
                    <span class="font-medium text-text-primary text-sm truncate">
                        {{ $this->offeringToRestore->name }}
                    </span>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" class="cursor-pointer">
                        {{ __('Cancelar') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="primary"
                    wire:click="restore"
                    wire:loading.attr="disabled"
                    class="cursor-pointer"
                    data-test="confirm-restore-btn"
                >
                    <span wire:loading.remove wire:target="restore">
                        {{ __('Restaurar servicio') }}
                    </span>
                    <span wire:loading wire:target="restore" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Restaurando...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Confirm Permanent Delete Modal --}}
    <flux:modal name="confirm-offering-permanent-delete" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-red-600 dark:text-red-400">
                    {{ __('¿Eliminar definitivamente este servicio?') }}
                </flux:heading>
                <flux:subheading class="mt-2 text-text-secondary">
                    {{ __('Esta acción no se puede deshacer. El producto o servicio y su imagen asociada se eliminarán permanentemente de la plataforma.') }}
                </flux:subheading>
            </div>

            @if ($this->offeringToDelete)
                <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-3 flex items-center gap-3 text-red-700 dark:text-red-400">
                    <flux:icon.trash class="size-5 shrink-0" />
                    <span class="font-medium text-sm truncate">
                        {{ $this->offeringToDelete->name }}
                    </span>
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
                    wire:click="deletePermanently"
                    wire:loading.attr="disabled"
                    class="cursor-pointer"
                    data-test="confirm-delete-btn"
                >
                    <span wire:loading.remove wire:target="deletePermanently">
                        {{ __('Eliminar definitivamente') }}
                    </span>
                    <span wire:loading wire:target="deletePermanently" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Eliminando...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
