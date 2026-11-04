<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Services\StorefrontService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Catálogo Comercial')] #[Layout('layouts.storefront')] class extends Component
{
    public string $slug = '';

    public ?int $businessId = null;

    public bool $isPreview = false;

    public bool $isAvailable = true;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $selectedCategoryId = null;

    public function mount(string $slug, StorefrontService $storefrontService): void
    {
        $this->slug = $slug;
        $business = $storefrontService->resolveBusiness($slug);

        if (! $business) {
            abort(404);
        }

        $currentUser = Auth::guard('business')->user() ?? Auth::user();
        $canView = $storefrontService->canBeViewedBy($business, $currentUser);

        if (! $canView) {
            $this->isAvailable = false;
        }

        if ($business->verification_status !== VerificationStatus::Approved && $currentUser?->id === $business->id && $currentUser instanceof Business) {
            $this->isPreview = true;
        }

        $this->businessId = $business->id;
    }

    #[Computed]
    public function business(): ?Business
    {
        if ($this->businessId === null) {
            return null;
        }

        return Business::find($this->businessId);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        if (! $this->business) {
            return new Collection;
        }

        return app(StorefrontService::class)->getActiveCategories($this->business);
    }

    /**
     * @return Collection<int, Offering>
     */
    #[Computed]
    public function offerings(): Collection
    {
        if (! $this->business) {
            return new Collection;
        }

        return app(StorefrontService::class)->getActiveStorefrontOfferings(
            $this->business,
            $this->search,
            $this->selectedCategoryId
        );
    }

    public function selectCategory(?int $categoryId = null): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->selectedCategoryId = null;
    }

    public function hasActiveFilters(): bool
    {
        return filled(trim($this->search)) || $this->selectedCategoryId !== null;
    }
};
?>

<div>
    @if (! $isAvailable)
    <div class="min-h-screen flex flex-col items-center justify-center p-6 bg-surface-canvas">
        <div class="w-full max-w-md rounded-2xl bg-surface-card border border-border-subtle p-8 text-center shadow-xs">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-surface-subtle text-text-secondary mb-4">
                <flux:icon.building-storefront class="size-7" />
            </div>
            <h1 class="text-xl font-bold text-text-primary tracking-tight">
                {{ __('Negocio no disponible') }}
            </h1>
            <p class="mt-2 text-sm text-text-secondary leading-relaxed">
                {{ __('Este negocio se encuentra en proceso de verificación o no está disponible públicamente en este momento.') }}
            </p>
            <div class="mt-6">
                <flux:button href="{{ route('home') }}" variant="primary" class="w-full">
                    {{ __('Volver al inicio') }}
                </flux:button>
            </div>
        </div>
    </div>
@else
    <div class="min-h-screen flex flex-col bg-surface-canvas">
        {{-- Owner Preview Warning Banner --}}
        @if ($isPreview)
            <div class="bg-amber-500/10 border-b border-amber-500/20 px-4 py-3 text-center text-sm font-medium text-amber-600 dark:text-amber-400">
                <div class="max-w-7xl mx-auto flex items-center justify-center gap-2">
                    <flux:icon.exclamation-triangle class="size-4 shrink-0" />
                    <span>{{ __('Modo Vista Previa: Tu negocio aún no está aprobado para el público general. Solo tú puedes ver esta página.') }}</span>
                </div>
            </div>
        @endif

        {{-- Top Navigation Bar --}}
        <header class="w-full border-b border-border-subtle bg-surface-card/80 backdrop-blur-md sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <a href="{{ route('home') }}" class="group inline-flex items-center gap-2 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-primary rounded-lg p-1">
                    <x-app-logo-icon class="size-7 fill-current text-brand-primary" />
                    <span class="font-black tracking-tight text-xl text-text-primary leading-none">
                        TURN<span class="text-brand-primary">EX</span>
                    </span>
                </a>

                <div class="flex items-center gap-3">
                    @if (auth('business')->check() || auth('customer')->check() || auth()->check())
                        <flux:button href="{{ route('dashboard') }}" variant="subtle" size="sm" icon="squares-2x2">
                            {{ __('Mi Panel') }}
                        </flux:button>
                    @else
                        <flux:button href="{{ route('login') }}" variant="subtle" size="sm">
                            {{ __('Iniciar Sesión') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </header>

        {{-- Main Content --}}
        <main class="flex-1 pb-16">
            @if ($this->business)
                {{-- Business Cover --}}
                <div class="relative w-full h-44 sm:h-60 md:h-72 bg-surface-subtle overflow-hidden">
                    @if ($this->business->cover_photo_path)
                        <img
                            src="{{ Storage::url($this->business->cover_photo_path) }}"
                            alt="{{ $this->business->business_name }}"
                            class="w-full h-full object-cover"
                        />
                    @else
                        <div class="w-full h-full bg-linear-to-r from-surface-subtle via-surface-card to-surface-subtle flex items-center justify-center opacity-60">
                            <flux:icon.photo class="size-12 text-text-secondary/40" />
                        </div>
                    @endif
                </div>

                {{-- Business Identity Header --}}
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="relative -mt-12 sm:-mt-16 flex flex-col sm:flex-row sm:items-end justify-between gap-4 pb-6 border-b border-border-subtle">
                        <div class="flex flex-col sm:flex-row sm:items-end gap-4 sm:gap-6">
                            {{-- Logo / Avatar --}}
                            <div class="shrink-0">
                                @if ($this->business->logo_path)
                                    <img
                                        src="{{ Storage::url($this->business->logo_path) }}"
                                        alt="{{ $this->business->business_name }}"
                                        class="size-24 sm:size-32 rounded-2xl object-cover border-4 border-surface-card bg-surface-card shadow-md"
                                    />
                                @else
                                    <div class="flex size-24 sm:size-32 items-center justify-center rounded-2xl border-4 border-surface-card bg-surface-subtle text-text-primary font-bold text-2xl sm:text-3xl shadow-md">
                                        {{ strtoupper(substr($this->business->business_name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Commercial Name, Category & Location --}}
                            <div class="pt-2 sm:pt-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-surface-subtle text-text-secondary border border-border-subtle">
                                        {{ $this->business->business_category instanceof \App\Enums\BusinessCategory ? $this->business->business_category->label() : $this->business->business_category }}
                                    </span>

                                    @if ($this->business->location)
                                        <span class="inline-flex items-center gap-1 text-xs sm:text-sm text-text-secondary">
                                            <flux:icon.map-pin class="size-3.5 shrink-0" />
                                            <span>{{ $this->business->location }}</span>
                                        </span>
                                    @endif
                                </div>

                                <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-text-primary tracking-tight">
                                    {{ $this->business->business_name }}
                                </h1>

                                @if ($this->business->business_description)
                                    <p class="mt-2 text-sm sm:text-base text-text-secondary max-w-2xl leading-relaxed">
                                        {{ $this->business->business_description }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Category Navigation & Search --}}
                    @if ($this->categories->isNotEmpty() || $this->hasActiveFilters())
                        <div class="mt-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            {{-- Category Filter Tabs --}}
                            @if ($this->categories->isNotEmpty())
                                <div class="flex items-center gap-2 overflow-x-auto pb-2 md:pb-0 scrollbar-none">
                                    <button
                                        type="button"
                                        wire:click="selectCategory"
                                        @class([
                                            'px-4 py-2 rounded-full text-sm font-medium transition-colors shrink-0 cursor-pointer',
                                            'bg-brand-primary text-white shadow-xs' => $selectedCategoryId === null,
                                            'bg-surface-subtle text-text-secondary hover:text-text-primary hover:bg-surface-card border border-border-subtle' => $selectedCategoryId !== null,
                                        ])
                                    >
                                        {{ __('Todos') }}
                                    </button>

                                    @foreach ($this->categories as $category)
                                        <button
                                            type="button"
                                            wire:key="category-tab-{{ $category->id }}"
                                            wire:click="selectCategory({{ $category->id }})"
                                            @class([
                                                'px-4 py-2 rounded-full text-sm font-medium transition-colors shrink-0 cursor-pointer',
                                                'bg-brand-primary text-white shadow-xs' => $selectedCategoryId === $category->id,
                                                'bg-surface-subtle text-text-secondary hover:text-text-primary hover:bg-surface-card border border-border-subtle' => $selectedCategoryId !== $category->id,
                                            ])
                                        >
                                            {{ $category->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Search Input --}}
                            <div class="w-full md:w-72 lg:w-80 shrink-0">
                                <flux:input
                                    wire:model.live.debounce.300ms="search"
                                    placeholder="{{ __('Buscar productos o servicios...') }}"
                                    icon="magnifying-glass"
                                    clearable
                                />
                            </div>
                        </div>
                    @endif

                    {{-- Offerings Catalog Grid or Empty States --}}
                    @if ($this->offerings->isEmpty())
                        @if ($this->hasActiveFilters())
                            {{-- Filter Miss Empty State --}}
                            <div class="mt-12 flex flex-col items-center justify-center p-8 rounded-2xl bg-surface-card border border-border-subtle text-center">
                                <div class="flex size-14 items-center justify-center rounded-full bg-surface-subtle text-text-secondary mb-4">
                                    <flux:icon.magnifying-glass class="size-6" />
                                </div>
                                <h3 class="text-base font-semibold text-text-primary">
                                    {{ __('No encontramos productos o servicios que coincidan con tu búsqueda.') }}
                                </h3>
                                <p class="mt-1 text-sm text-text-secondary max-w-sm">
                                    {{ __('Prueba ajustando los términos de búsqueda o eliminando los filtros seleccionados.') }}
                                </p>
                                <div class="mt-6">
                                    <flux:button wire:click="resetFilters" variant="filled" size="sm">
                                        {{ __('Restablecer filtros') }}
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            {{-- Zero Offerings Empty State --}}
                            <div class="mt-12 flex flex-col items-center justify-center p-12 rounded-2xl bg-surface-card border border-border-subtle text-center">
                                <div class="flex size-14 items-center justify-center rounded-full bg-surface-subtle text-text-secondary mb-4">
                                    <flux:icon.shopping-bag class="size-6" />
                                </div>
                                <h3 class="text-base font-semibold text-text-primary">
                                    {{ __('Este negocio aún no tiene productos o servicios disponibles.') }}
                                </h3>
                                <p class="mt-1 text-sm text-text-secondary max-w-sm">
                                    {{ __('Vuelve a consultar más tarde para descubrir las novedades de este catálogo.') }}
                                </p>
                            </div>
                        @endif
                    @else
                        {{-- Catalog Grid --}}
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($this->offerings as $offering)
                                <div wire:key="offering-{{ $offering->id }}" class="flex flex-col rounded-2xl bg-surface-card border border-border-subtle overflow-hidden shadow-xs hover:border-border-strong transition-colors">
                                    {{-- Reference Image / Fallback --}}
                                    <div class="relative w-full h-48 bg-surface-subtle overflow-hidden flex items-center justify-center">
                                        @if ($offering->reference_image)
                                            <img
                                                src="{{ Storage::url($offering->reference_image) }}"
                                                alt="{{ $offering->name }}"
                                                class="w-full h-full object-cover"
                                                loading="lazy"
                                            />
                                        @else
                                            <div class="flex items-center justify-center text-text-secondary">
                                                <flux:icon.shopping-bag class="size-10 stroke-1" />
                                            </div>
                                        @endif

                                        @if ($offering->category)
                                            <div class="absolute top-3 left-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-card/90 backdrop-blur-xs text-text-primary border border-border-subtle shadow-xs">
                                                    {{ $offering->category->name }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Offering Body --}}
                                    <div class="flex flex-col flex-1 p-5">
                                        <h2 class="text-base font-semibold text-text-primary leading-snug">
                                            {{ $offering->name }}
                                        </h2>

                                        @if ($offering->description)
                                            <p class="mt-2 text-sm text-text-secondary line-clamp-2 leading-relaxed">
                                                {{ $offering->description }}
                                            </p>
                                        @endif

                                        <div class="mt-auto pt-4 flex items-center justify-between border-t border-border-subtle">
                                            <div>
                                                @if ((float) $offering->price === 0.0 || $offering->price == 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-surface-subtle text-text-secondary">
                                                        {{ __('Gratis') }}
                                                    </span>
                                                @else
                                                    <span class="text-base font-bold text-text-primary">
                                                        $ {{ number_format((float) $offering->price, 0, ',', '.') }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if ($offering->estimated_duration_minutes)
                                                <span class="inline-flex items-center gap-1 text-xs text-text-secondary font-medium">
                                                    <span>⏱</span>
                                                    <span>{{ $offering->estimated_duration_minutes }} min</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </main>

        {{-- Footer --}}
        <footer class="mt-auto border-t border-border-subtle py-8 text-center text-xs text-text-secondary">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p>&copy; {{ date('Y') }} {{ $this->business?->business_name ?? config('app.name', 'Turnex') }}. {{ __('Todos los derechos reservados.') }}</p>
                <p class="flex items-center gap-1">
                    <span>{{ __('Impulsado por') }}</span>
                    <a href="{{ route('home') }}" class="font-bold text-text-primary hover:text-brand-primary transition-colors">TURNEX</a>
                </p>
            </div>
        </footer>
    </div>
@endif
</div>
