@php
    $business = Auth::guard('business')->user() ?? Auth::user();
    $businessName = $business?->business_name ?? $business?->name ?? __('Mi Negocio');
    $businessEmail = $business?->email ?? '';
    $businessInitials = method_exists($business, 'initials') ? $business->initials() : 'TN';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-surface-canvas text-text-primary antialiased">
        <flux:sidebar
            sticky
            :collapsible="true"
            class="max-lg:hidden border-e border-border-subtle bg-surface-card transition-all! duration-200 ease-in-out in-data-flux-sidebar-collapsed-desktop:cursor-pointer!"
        >
            <flux:sidebar.header class="min-h-12 gap-2 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:px-0">
                {{-- Expanded Brand & Business Context --}}
                <div class="flex min-w-0 flex-1 items-center overflow-hidden in-data-flux-sidebar-collapsed-desktop:hidden">
                    <div class="grid flex-1 text-start leading-tight overflow-hidden">
                        <span class="font-black tracking-tight text-text-primary text-base leading-none">TURN<span class="text-brand-primary">EX</span></span>
                        <span class="truncate text-xs text-text-secondary mt-1">{{ $businessName }}</span>
                    </div>
                </div>

                {{-- Collapsed Brand Monogram (Acts as Expand Toggle) --}}
                <ui-sidebar-toggle class="hidden in-data-flux-sidebar-collapsed-desktop:flex items-center justify-center cursor-pointer" data-flux-sidebar-collapse>
                    <flux:tooltip :content="__('Expandir menú lateral')" position="right">
                        <button type="button" class="flex size-10 items-center justify-center rounded-lg hover:bg-surface-subtle transition-colors cursor-pointer">
                            <span class="font-black text-lg tracking-tight text-text-primary leading-none select-none">T<span class="text-brand-primary">X</span></span>
                        </button>
                    </flux:tooltip>
                </ui-sidebar-toggle>

                <flux:sidebar.collapse
                    :tooltip="__('Alternar menú lateral')"
                    class="ms-auto cursor-pointer [&_button]:cursor-pointer in-data-flux-sidebar-collapsed-desktop:hidden!"
                />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-5">
                <div class="flex flex-col gap-0.5">
                    <div class="px-3 pb-1 pt-1 text-xs font-medium text-text-secondary in-data-flux-sidebar-collapsed-desktop:hidden">
                        {{ __('Operación') }}
                    </div>
                    <flux:sidebar.item
                        icon="queue-list"
                        :href="route('business.queue.index')"
                        :current="request()->routeIs('business.queue.*') || request()->is('business/queue*') || request()->is('business/live-queue*')"
                        wire:navigate
                        class="cursor-pointer transition-colors data-current:border-transparent! data-current:bg-brand-primary/10! data-current:text-brand-primary! hover:bg-surface-subtle! hover:text-text-primary!"
                    >
                        {{ __('Turnos en Vivo') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="chart-bar-square"
                        :href="Route::has('business.dashboard') ? route('business.dashboard') : url('/business/dashboard')"
                        :current="request()->routeIs('business.dashboard') || request()->is('business/dashboard*')"
                        wire:navigate
                        class="cursor-pointer transition-colors data-current:border-transparent! data-current:bg-brand-primary/10! data-current:text-brand-primary! hover:bg-surface-subtle! hover:text-text-primary!"
                    >
                        {{ __('Métricas') }}
                    </flux:sidebar.item>
                </div>

                <div class="flex flex-col gap-0.5">
                    <div class="px-3 pb-1 pt-1 text-xs font-medium text-text-secondary in-data-flux-sidebar-collapsed-desktop:hidden">
                        {{ __('Gestión') }}
                    </div>
                    <flux:sidebar.item
                        icon="shopping-bag"
                        :href="route('business.offerings.index')"
                        :current="request()->routeIs('business.offerings.*') || request()->is('business/offerings*')"
                        wire:navigate
                        class="cursor-pointer transition-colors data-current:border-transparent! data-current:bg-brand-primary/10! data-current:text-brand-primary! hover:bg-surface-subtle! hover:text-text-primary!"
                    >
                        {{ __('Productos y Servicios') }}
                    </flux:sidebar.item>
                </div>

                <div class="flex flex-col gap-0.5">
                    <div class="px-3 pb-1 pt-1 text-xs font-medium text-text-secondary in-data-flux-sidebar-collapsed-desktop:hidden">
                        {{ __('Configuración') }}
                    </div>
                    <flux:sidebar.item
                        icon="building-storefront"
                        :href="url('/business/profile')"
                        :current="request()->is('business/profile*')"
                        wire:navigate
                        class="cursor-pointer transition-colors data-current:border-transparent! data-current:bg-brand-primary/10! data-current:text-brand-primary! hover:bg-surface-subtle! hover:text-text-primary!"
                    >
                        {{ __('Perfil') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="shield-check"
                        :href="url('/business/verification')"
                        :current="request()->is('business/verification*')"
                        wire:navigate
                        class="cursor-pointer transition-colors data-current:border-transparent! data-current:bg-brand-primary/10! data-current:text-brand-primary! hover:bg-surface-subtle! hover:text-text-primary!"
                    >
                        {{ __('Verificar Negocio') }}
                    </flux:sidebar.item>
                </div>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="border-t border-border-subtle pt-3 in-data-flux-sidebar-collapsed-desktop:border-transparent">
                <flux:dropdown position="bottom" align="start">
                    <flux:sidebar.profile
                        :name="$businessName"
                        :initials="$businessInitials"
                        icon:trailing="chevrons-up-down"
                        data-test="business-sidebar-menu-button"
                        class="cursor-pointer in-data-flux-sidebar-collapsed-desktop:justify-center"
                    />

                    <flux:menu>
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar
                                :name="$businessName"
                                :initials="$businessInitials"
                            />
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ $businessName }}</flux:heading>
                                <flux:text class="truncate">{{ $businessEmail }}</flux:text>
                            </div>
                        </div>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('business.logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                                data-test="business-logout-button"
                            >
                                {{ __('Cerrar sesión') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden border-b border-border-subtle bg-surface-card">
            <x-app-brand :href="route('business.queue.index')" size="sm" />

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :name="$businessName"
                    :initials="$businessInitials"
                    icon-trailing="chevron-down"
                    class="cursor-pointer max-md:[&>span]:hidden"
                    data-test="business-mobile-menu-button"
                />

                <flux:menu class="w-56">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar
                            :name="$businessName"
                            :initials="$businessInitials"
                        />

                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ $businessName }}</flux:heading>
                            <flux:text class="truncate text-xs">{{ $businessEmail }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('business.logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer text-red-500 hover:text-red-600 dark:text-red-400"
                            data-test="business-mobile-logout-button"
                        >
                            {{ __('Cerrar sesión') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <!-- Mobile Bottom Navigation Bar (Fixed for Business) -->
        <nav aria-label="Navegación móvil del negocio" class="fixed bottom-0 inset-x-0 z-40 lg:hidden bg-surface-card/95 backdrop-blur-md border-t border-border-subtle px-2 py-2 safe-bottom">
            <div class="grid grid-cols-4 items-center">
                <a
                    href="{{ route('business.queue.index') }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ request()->routeIs('business.queue.*') || request()->is('business/queue*') || request()->is('business/live-queue*') ? 'text-brand-primary font-semibold' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="business-mobile-nav-queue"
                >
                    <flux:icon.queue-list class="size-5" />
                    <span>{{ __('Fila') }}</span>
                </a>

                <a
                    href="{{ Route::has('business.dashboard') ? route('business.dashboard') : url('/business/dashboard') }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ request()->routeIs('business.dashboard') || request()->is('business/dashboard*') ? 'text-brand-primary font-semibold' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="business-mobile-nav-dashboard"
                >
                    <flux:icon.chart-bar-square class="size-5" />
                    <span>{{ __('Métricas') }}</span>
                </a>

                <a
                    href="{{ route('business.offerings.index') }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ request()->routeIs('business.offerings.*') || request()->is('business/offerings*') ? 'text-brand-primary font-semibold' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="business-mobile-nav-offerings"
                >
                    <flux:icon.shopping-bag class="size-5" />
                    <span>{{ __('Productos') }}</span>
                </a>

                <a
                    href="{{ url('/business/profile') }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ request()->is('business/profile*') || request()->is('business/verification*') ? 'text-brand-primary font-semibold' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="business-mobile-nav-profile"
                >
                    <flux:icon.building-storefront class="size-5" />
                    <span>{{ __('Negocio') }}</span>
                </a>
            </div>
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

