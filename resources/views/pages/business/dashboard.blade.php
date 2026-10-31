<x-layouts::business :title="__('Dashboard')">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">
                    {{ __('Panel de Control') }}
                </h1>
                <p class="mt-1 text-sm text-text-secondary">
                    {{ __('Bienvenido al centro de operaciones y gestión de tu negocio.') }}
                </p>
            </div>
            <div>
                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('business.offerings.create')"
                    wire:navigate
                    class="cursor-pointer"
                >
                    {{ __('Nuevo Producto o Servicio') }}
                </flux:button>
            </div>
        </div>

        {{-- Setup Mode Notification (after initial registration) --}}
        @if (request()->has('setup'))
            <div class="rounded-xl border border-brand-primary/30 bg-brand-primary/10 p-5 text-text-primary" role="alert">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-primary text-white">
                        <flux:icon.sparkles class="size-4" />
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-semibold text-text-primary">
                            {{ __('¡Configuración inicial de tu negocio!') }}
                        </h4>
                        <p class="text-sm text-text-secondary">
                            {{ __('Tu cuenta ha sido creada exitosamente. Para que tus clientes puedan solicitar turnos y realizar pedidos, crea tu primer producto o servicio en el catálogo.') }}
                        </p>
                        <div class="pt-2">
                            <flux:button
                                variant="primary"
                                size="sm"
                                icon="arrow-right"
                                :href="route('business.offerings.create')"
                                wire:navigate
                                class="cursor-pointer"
                            >
                                {{ __('Comenzar con el catálogo') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Overview Metrics Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border-subtle bg-surface-card p-5">
                <div class="flex items-center justify-between text-text-secondary">
                    <span class="text-xs font-semibold uppercase tracking-wider">{{ __('Turnos hoy') }}</span>
                    <flux:icon.queue-list class="size-5 text-text-secondary" />
                </div>
                <p class="mt-2 text-3xl font-bold font-mono text-text-primary">0</p>
                <span class="mt-1 block text-xs text-text-secondary">{{ __('En todas las colas activas') }}</span>
            </div>

            <div class="rounded-xl border border-border-subtle bg-surface-card p-5">
                <div class="flex items-center justify-between text-text-secondary">
                    <span class="text-xs font-semibold uppercase tracking-wider">{{ __('En espera') }}</span>
                    <flux:icon.clock class="size-5 text-amber-500" />
                </div>
                <p class="mt-2 text-3xl font-bold font-mono text-text-primary">0</p>
                <span class="mt-1 block text-xs text-text-secondary">{{ __('Clientes aguardando llamado') }}</span>
            </div>

            <div class="rounded-xl border border-border-subtle bg-surface-card p-5">
                <div class="flex items-center justify-between text-text-secondary">
                    <span class="text-xs font-semibold uppercase tracking-wider">{{ __('Catálogo activo') }}</span>
                    <flux:icon.shopping-bag class="size-5 text-brand-primary" />
                </div>
                <p class="mt-2 text-3xl font-bold font-mono text-text-primary">
                    {{ Auth::guard('business')->user()?->offerings()->count() ?? 0 }}
                </p>
                <span class="mt-1 block text-xs text-text-secondary">
                    <a href="{{ route('business.offerings.index') }}" wire:navigate class="text-brand-primary hover:underline">
                        {{ __('Ver catálogo de productos →') }}
                    </a>
                </span>
            </div>
        </div>
    </div>
</x-layouts::business>

