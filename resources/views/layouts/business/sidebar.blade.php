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
        <flux:sidebar sticky collapsible="mobile" class="border-e border-border-subtle bg-surface-card">
            <flux:sidebar.header class="gap-3">
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <flux:avatar
                        :name="$businessName"
                        :initials="$businessInitials"
                        class="bg-brand-primary text-white font-semibold shrink-0"
                    />
                    <div class="grid flex-1 text-start leading-tight overflow-hidden">
                        <span class="truncate font-semibold text-text-primary text-sm">{{ $businessName }}</span>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="inline-block size-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs text-text-secondary font-medium">{{ __('En línea') }}</span>
                        </div>
                    </div>
                </div>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Operación')" class="grid">
                    <flux:sidebar.item
                        icon="queue-list"
                        :href="url('/business/live-queue')"
                        :current="request()->is('business/live-queue*')"
                        wire:navigate
                    >
                        {{ __('Turnos en Vivo') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="chart-bar-square"
                        :href="Route::has('business.dashboard') ? route('business.dashboard') : url('/business/dashboard')"
                        :current="request()->routeIs('business.dashboard') || request()->is('business/dashboard*')"
                        wire:navigate
                    >
                        {{ __('Métricas') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Gestión')" class="grid">
                    <flux:sidebar.item
                        icon="shopping-bag"
                        :href="route('business.offerings.index')"
                        :current="request()->routeIs('business.offerings.*') || request()->is('business/offerings*')"
                        wire:navigate
                    >
                        {{ __('Productos y Servicios') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Configuración')" class="grid">
                    <flux:sidebar.item
                        icon="building-storefront"
                        :href="url('/business/profile')"
                        :current="request()->is('business/profile*')"
                        wire:navigate
                    >
                        {{ __('Perfil') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="shield-check"
                        :href="url('/business/verification')"
                        :current="request()->is('business/verification*')"
                        wire:navigate
                    >
                        {{ __('Verificación KYB') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="border-t border-border-subtle pt-3">
                <flux:dropdown position="bottom" align="start">
                    <flux:sidebar.profile
                        :name="$businessName"
                        :initials="$businessInitials"
                        icon:trailing="chevrons-up-down"
                        data-test="business-sidebar-menu-button"
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
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="$businessInitials"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <div class="p-0 text-sm font-normal">
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
                    </div>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('business.logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="business-mobile-logout-button"
                        >
                            {{ __('Cerrar sesión') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

