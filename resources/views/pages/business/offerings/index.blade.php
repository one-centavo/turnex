<x-layouts::business :title="__('Productos y Servicios')">
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
    </div>
</x-layouts::business>

