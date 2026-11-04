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
            collapsible="true"
            class="border-e border-border-subtle bg-surface-card transition-all! duration-200 ease-in-out in-data-flux-sidebar-collapsed-desktop:cursor-pointer!"
        >
            <flux:sidebar.header class="min-h-12 gap-2 in-data-flux-sidebar-collapsed-desktop:justify-center">
                <div class="flex min-w-0 flex-1 items-center gap-2.5 overflow-hidden in-data-flux-sidebar-collapsed-desktop:hidden">
                    <flux:avatar
                        :name="$businessName"
                        :initials="$businessInitials"
                        class="bg-brand-primary text-white font-semibold shrink-0"
                    />
                    <div class="grid flex-1 text-start leading-tight overflow-hidden">
                        <span class="truncate font-semibold text-text-primary text-sm">{{ $businessName }}</span>
                    </div>
                </div>

                <flux:sidebar.collapse
                    :tooltip="__('Alternar menú lateral')"
                    class="ms-auto cursor-pointer [&_button]:cursor-pointer in-data-flux-sidebar-collapsed-desktop:static! in-data-flux-sidebar-collapsed-desktop:mx-auto in-data-flux-sidebar-collapsed-desktop:opacity-100!"
                />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-5">
                <div class="flex flex-col gap-0.5">
                    <div class="px-3 pb-1 pt-1 text-xs font-medium text-text-secondary in-data-flux-sidebar-collapsed-desktop:hidden">
                        {{ __('Operación') }}
                    </div>
                    <flux:sidebar.item
                        icon="queue-list"
                        :href="url('/business/live-queue')"
                        :current="request()->is('business/live-queue*')"
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
                        {{ __('Verificación KYB') }}
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
            <flux:sidebar.toggle class="cursor-pointer lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="$businessInitials"
                    icon-trailing="chevron-down"
                    class="cursor-pointer"
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

