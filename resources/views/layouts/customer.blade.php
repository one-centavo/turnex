@props([
    'title' => null,
])
@php
    /** @var \App\Models\Customer|null $customer */
    $customer = auth()->guard('customer')->user() ?? auth()->user();
    $customerName = $customer?->name ?? 'Cliente';
    $customerInitials = method_exists($customer ?? '', 'initials') ? $customer->initials() : 'CL';
    $customerEmail = $customer?->email ?? '';
    $customerAvatar = $customer?->avatar_url ?? null;

    $ticketsUrl = Route::has('customer.tickets') ? route('customer.tickets') : url('/my-tickets');
    $exploreUrl = Route::has('explore') ? route('explore') : url('/explore');
    $profileUrl = Route::has('customer.profile') ? route('customer.profile') : url('/customer/profile');
    $logoutUrl = Route::has('customer.logout') ? route('customer.logout') : (Route::has('logout') ? route('logout') : url('/customer/logout'));
    $loginUrl = Route::has('customer.login') ? route('customer.login') : (Route::has('login') ? route('login') : url('/login'));
    $registerUrl = Route::has('customer.register') ? route('customer.register') : (Route::has('register') ? route('register') : url('/register'));

    $isTicketsActive = request()->routeIs('customer.tickets*');
    $isExploreActive = request()->routeIs('explore*');
    $isProfileActive = request()->routeIs('customer.profile*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-surface-canvas text-text-primary font-sans antialiased selection:bg-brand-primary selection:text-white">
        <!-- Top Navigation Header -->
        <header class="sticky top-0 z-30 border-b border-border-subtle bg-surface-card/90 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <x-app-brand :href="$customer ? $ticketsUrl : $exploreUrl" size="sm" />

                    <!-- Desktop Navigation Links -->
                    <nav class="hidden md:flex items-center gap-6">
                        <a
                            href="{{ $exploreUrl }}"
                            wire:navigate
                            class="text-sm font-medium transition-colors {{ $isExploreActive ? 'text-brand-primary' : 'text-text-secondary hover:text-text-primary' }}"
                        >
                            Explorar
                        </a>

                        <a
                            href="{{ $customer ? $ticketsUrl : $loginUrl }}"
                            wire:navigate
                            class="text-sm font-medium transition-colors {{ $isTicketsActive ? 'text-brand-primary' : 'text-text-secondary hover:text-text-primary' }}"
                        >
                            Mis Turnos
                        </a>
                    </nav>
                </div>

                <!-- Customer User Menu / Auth Actions -->
                <div class="flex items-center gap-4">
                    @if ($customer)
                        <flux:dropdown position="bottom" align="end">
                            <flux:profile
                                :name="$customerName"
                                :initials="$customerInitials"
                                :avatar="$customerAvatar"
                                icon-trailing="chevron-down"
                                class="cursor-pointer max-md:[&>span]:hidden max-md:[&>div:last-child]:hidden"
                                data-test="customer-menu-button"
                            />

                            <flux:menu class="w-56">
                                <div class="flex items-center gap-2 px-2 py-2 text-start text-sm">
                                    <flux:avatar
                                        :name="$customerName"
                                        :initials="$customerInitials"
                                        :src="$customerAvatar"
                                    />

                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <flux:heading class="truncate font-medium text-text-primary">{{ $customerName }}</flux:heading>
                                        @if(filled($customerEmail))
                                            <flux:text class="truncate text-xs text-text-secondary">{{ $customerEmail }}</flux:text>
                                        @endif
                                    </div>
                                </div>

                                <flux:menu.separator />

                                @if(Route::has('customer.profile'))
                                    <flux:menu.item :href="route('customer.profile')" icon="user" wire:navigate>
                                        Perfil
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                @endif

                                <form method="POST" action="{{ $logoutUrl }}" class="w-full">
                                    @csrf
                                    <flux:menu.item
                                        as="button"
                                        type="submit"
                                        icon="arrow-right-start-on-rectangle"
                                        class="w-full cursor-pointer text-red-500 hover:text-red-600 dark:text-red-400"
                                        data-test="customer-logout-button"
                                    >
                                        Cerrar Sesión
                                    </flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <div class="flex items-center gap-3">
                            <a
                                href="{{ $loginUrl }}"
                                wire:navigate
                                class="text-sm font-medium text-text-secondary hover:text-text-primary transition-colors px-3 py-1.5"
                                data-test="customer-login-link"
                            >
                                Iniciar sesión
                            </a>

                            <a
                                href="{{ $registerUrl }}"
                                wire:navigate
                                class="inline-flex items-center justify-center h-9 px-4 text-sm font-semibold rounded-md bg-brand-primary hover:opacity-90 text-white transition-opacity focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-primary shadow-xs"
                                data-test="customer-register-link"
                            >
                                Registrarse
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="pb-24 md:pb-12">
            {{ $slot }}
        </main>

        <!-- Mobile Bottom Navigation Bar -->
        <nav aria-label="Navegación móvil" class="fixed bottom-0 inset-x-0 z-40 md:hidden bg-surface-card/90 backdrop-blur-md border-t border-border-subtle px-4 py-2 safe-bottom">
            <div class="grid grid-cols-3 items-center">
                <a
                    href="{{ $exploreUrl }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ $isExploreActive ? 'text-brand-primary' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="mobile-nav-explore"
                >
                    <flux:icon.magnifying-glass class="size-5" />
                    <span>Explorar</span>
                </a>

                <a
                    href="{{ $customer ? $ticketsUrl : $loginUrl }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ $isTicketsActive ? 'text-brand-primary' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="mobile-nav-tickets"
                >
                    <flux:icon.ticket class="size-5" />
                    <span>Mis Turnos</span>
                </a>

                <a
                    href="{{ $customer ? $profileUrl : $loginUrl }}"
                    wire:navigate
                    class="flex flex-col items-center justify-center gap-1 py-1 text-xs font-medium transition-colors {{ $isProfileActive ? 'text-brand-primary' : 'text-text-secondary hover:text-text-primary' }}"
                    data-test="mobile-nav-profile"
                >
                    <flux:icon.user class="size-5" />
                    <span>Perfil</span>
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
