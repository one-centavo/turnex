<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen flex flex-col justify-between bg-surface-canvas text-text-primary antialiased selection:bg-blue-600 selection:text-white font-sans">
        {{-- Top Navigation Header (Full Width) --}}
        <header class="w-full px-6 sm:px-8 lg:px-12 py-5 flex items-center justify-between">
            {{-- Turnex Brand Logo --}}
            <a href="{{ route('home') }}" class="group inline-flex flex-col focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 rounded-md p-1">
                <span class="font-black tracking-tight text-xl sm:text-2xl text-slate-900 dark:text-slate-100 leading-none">
                    TURN<span class="text-blue-600 dark:text-blue-500">EX</span>
                </span>
                <span class="font-mono text-xs tracking-widest text-slate-500 dark:text-slate-400 uppercase mt-1">
                    Queue Platform
                </span>
            </a>

            {{-- Actions: Theme Switcher & Authentication --}}
            <div class="flex items-center gap-2 sm:gap-3">
                {{-- Theme Switcher (Flux / Alpine) --}}
                <button
                    type="button"
                    x-data
                    @click="$flux.dark = !$flux.dark"
                    class="inline-flex items-center justify-center size-9 rounded-md border border-border-subtle bg-surface-card text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-700 transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 cursor-pointer"
                    aria-label="{{ __('Alternar tema') }}"
                    title="{{ __('Alternar modo claro / oscuro') }}"
                >
                    {{-- Sun icon when dark --}}
                    <svg x-show="$flux.dark" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    {{-- Moon icon when light --}}
                    <svg x-show="!$flux.dark" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                @if (auth('business')->check())
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center h-9 px-4 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600"
                    >
                        {{ __('Panel de Negocio') }}
                    </a>
                @elseif (auth('customer')->check())
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center h-9 px-4 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600"
                    >
                        {{ __('Mi Panel') }}
                    </a>
                @elseif (auth()->check())
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center h-9 px-4 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600"
                    >
                        {{ __('Dashboard') }}
                    </a>
                @else
                    {{-- Unified Login Dropdown (Hover on Desktop / Click on Mobile) --}}
                    <div
                        x-data="{
                            open: false,
                            timeout: null,
                            toggle() { this.open = !this.open },
                            close() { this.open = false },
                            hoverEnter() {
                                clearTimeout(this.timeout);
                                this.open = true;
                            },
                            hoverLeave() {
                                this.timeout = setTimeout(() => { this.open = false }, 180);
                            }
                        }"
                        @keydown.escape.stop="close()"
                        @click.outside="close()"
                        @mouseenter="hoverEnter()"
                        @mouseleave="hoverLeave()"
                        class="relative inline-flex items-center"
                    >
                        <button
                            type="button"
                            @click="toggle()"
                            class="inline-flex items-center justify-center size-9 sm:size-auto sm:h-9 sm:px-3 rounded-md border border-border-subtle sm:border-transparent bg-surface-card sm:bg-transparent text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-700 sm:hover:border-transparent transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 cursor-pointer"
                            :aria-expanded="open"
                            aria-label="{{ __('Menú de navegación') }}"
                        >
                            {{-- Mobile Hamburger / Close Icon --}}
                            <span class="sm:hidden flex items-center justify-center">
                                <svg x-show="!open" class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                                <svg x-show="open" x-cloak class="size-4.5 text-rose-500 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </span>

                            {{-- Desktop Text & Chevron --}}
                            <span class="hidden sm:inline font-medium text-sm whitespace-nowrap">{{ __('Iniciar sesión') }}</span>
                            <svg class="hidden sm:inline size-4 text-slate-400 transition-transform duration-150 shrink-0" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            x-cloak
                            class="absolute right-0 top-full mt-1.5 w-60 rounded-lg border border-border-subtle bg-surface-card p-1.5 shadow-lg shadow-black/5 dark:shadow-black/20 z-50 flex flex-col gap-0.5"
                        >
                            <div class="px-2.5 py-1 text-xs font-mono uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                {{ __('Iniciar sesión') }}
                            </div>

                            <a
                                href="{{ route('login') }}"
                                class="flex items-center gap-2.5 px-2.5 py-2 text-sm rounded-md font-medium text-slate-700 dark:text-slate-200 hover:bg-surface-subtle hover:text-blue-600 dark:hover:text-blue-400 transition-colors group"
                                wire:navigate
                            >
                                <div class="flex items-center justify-center size-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 group-hover:bg-blue-50 dark:group-hover:bg-blue-950/40 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors shrink-0">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </div>
                                <div class="flex flex-col text-left">
                                    <span class="leading-tight">{{ __('Como Cliente') }}</span>
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ __('Turnos y pedidos') }}</span>
                                </div>
                            </a>

                            <a
                                href="{{ route('business.login') }}"
                                class="flex items-center gap-2.5 px-2.5 py-2 text-sm rounded-md font-medium text-slate-700 dark:text-slate-200 hover:bg-surface-subtle hover:text-blue-600 dark:hover:text-blue-400 transition-colors group"
                                wire:navigate
                            >
                                <div class="flex items-center justify-center size-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 group-hover:bg-blue-50 dark:group-hover:bg-blue-950/40 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors shrink-0">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.651V9.35m0 0a3.001 3.001 0 003.75-.614A2.993 2.993 0 009 9.35c.983-.913 2.502-.913 3.486 0a2.993 2.993 0 003.264-.614 3.001 3.001 0 003.75.614m-16.5 0a2.997 2.997 0 01-.614-1.25L3.6 4.75A2.25 2.25 0 015.82 3h12.36a2.25 2.25 0 012.22 1.75l1.214 3.35c.084.232.126.476.126.72 0 .47-.11.916-.307 1.314" />
                                    </svg>
                                </div>
                                <div class="flex flex-col text-left">
                                    <span class="leading-tight">{{ __('Como Negocio') }}</span>
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ __('Panel de operaciones') }}</span>
                                </div>
                            </a>

                            {{-- Mobile-only registration shortcuts (when desktop header buttons are hidden) --}}
                            <div class="sm:hidden border-t border-border-subtle my-1"></div>
                            <div class="sm:hidden px-2.5 py-1 text-xs font-mono uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                {{ __('Crear cuenta') }}
                            </div>

                            <a
                                href="{{ route('register') }}"
                                class="sm:hidden flex items-center gap-2.5 px-2.5 py-2 text-sm rounded-md font-medium text-slate-700 dark:text-slate-200 hover:bg-surface-subtle hover:text-blue-600 dark:hover:text-blue-400 transition-colors group"
                                wire:navigate
                            >
                                <div class="flex items-center justify-center size-7 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 transition-colors shrink-0">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </div>
                                <div class="flex flex-col text-left">
                                    <span class="leading-tight">{{ __('Registrarse como Cliente') }}</span>
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ __('Obtén turnos virtuales') }}</span>
                                </div>
                            </a>

                            <a
                                href="{{ route('business.register') }}"
                                class="sm:hidden flex items-center gap-2.5 px-2.5 py-2 text-sm rounded-md font-medium text-slate-700 dark:text-slate-200 hover:bg-surface-subtle hover:text-blue-600 dark:hover:text-blue-400 transition-colors group"
                                wire:navigate
                            >
                                <div class="flex items-center justify-center size-7 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 transition-colors shrink-0">
                                    <flux:icon name="building-storefront" class="size-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="flex flex-col text-left">
                                    <span class="leading-tight">{{ __('Registrar mi Negocio') }}</span>
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ __('Comienza en 2 minutos') }}</span>
                                </div>
                            </a>
                        </div>
                    </div>
                    <a
                        href="{{ route('business.register') }}"
                        class="hidden sm:inline-flex items-center justify-center h-9 px-3.5 text-sm font-medium rounded-md border border-border-strong bg-surface-card text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:border-slate-400 dark:hover:border-slate-600 transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600"
                    >
                        {{ __('Registrar Negocio') }}
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="hidden sm:inline-flex items-center justify-center h-9 px-3.5 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 shadow-xs"
                    >
                        {{ __('Registrarse') }}
                    </a>
                @endif
            </div>
        </header>

        {{-- Main Hero Section (Split Layout: Text Left / Line-Art Illustration Right) --}}
        <main class="w-full px-6 sm:px-8 lg:px-12 pt-4 sm:pt-6 lg:pt-8 pb-12 flex-1 flex flex-col justify-start">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-8 xl:gap-10 items-center w-full">
                {{-- Left Column: Value Proposition & CTAs --}}
                <div class="lg:col-span-7 xl:col-span-6 flex flex-col items-start gap-5 sm:gap-6">
                    {{-- Main Headline --}}
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 dark:text-slate-100 leading-tight">
                        {{ __('Dile adiós a las filas físicas.') }}
                        <span class="block text-blue-600 dark:text-blue-500 mt-1 sm:mt-2">
                            {{ __('Gestiona turnos y pedidos en tiempo real.') }}
                        </span>
                    </h1>

                    {{-- Subheadline --}}
                    <p class="text-base sm:text-lg lg:text-xl text-slate-600 dark:text-slate-400 max-w-2xl leading-relaxed">
                        {{ __('Turnex elimina la congestión y la incertidumbre en comercios y salas de espera. Solicita tu turno virtual, sigue tu posición desde el móvil y acércate solo cuando sea tu momento.') }}
                    </p>

                    {{-- CTAs Row --}}
                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 pt-1 w-full sm:w-auto">
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center h-11 px-6 text-base font-semibold rounded-md bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 shadow-sm w-full sm:w-auto"
                        >
                            <span>{{ __('Ingresar como Cliente') }}</span>
                            <svg class="size-4.5 ms-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>

                        <a
                            href="{{ route('business.register') }}"
                            class="inline-flex items-center justify-center gap-2.5 h-11 px-5 text-sm sm:text-base font-semibold rounded-md border border-border-strong bg-surface-card text-slate-800 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-500 dark:hover:border-blue-500 transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 w-full sm:w-auto shadow-xs"
                        >
                            <flux:icon name="building-storefront" class="size-5 text-blue-600 dark:text-blue-400 shrink-0" />
                            <span>{{ __('Registrar mi Negocio') }}</span>
                        </a>
                    </div>

                    {{-- Business Login Link for returning merchants --}}
                    @guest('business')
                        <div class="text-xs text-slate-600 dark:text-slate-400 -mt-2">
                            {{ __('¿Ya registraste tu empresa?') }}
                            <a href="{{ route('business.login') }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ __('Inicia sesión aquí') }}
                            </a>
                        </div>
                    @endguest

                    {{-- Micro Indicators --}}
                    <div class="pt-3 flex flex-wrap items-center gap-y-2 gap-x-6 text-xs text-slate-500 dark:text-slate-400 font-mono">
                        <div class="inline-flex items-center gap-1.5">
                            <svg class="size-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span>{{ __('Sin apps obligatorias') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <svg class="size-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span>{{ __('Alertas WhatsApp / SMS') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <svg class="size-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span>{{ __('Alta de comercios en 2 min') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Line-Art Narrative Illustration & Comparative List --}}
                <div class="lg:col-span-5 xl:col-span-6 flex justify-start w-full">
                    <div class="w-full max-w-xl xl:max-w-2xl flex flex-col gap-5 select-none">
                        <svg viewBox="0 0 520 270" class="w-full h-auto text-slate-800 dark:text-slate-200" fill="none" xmlns="http://www.w3.org/2000/svg">
                            {{-- Background Grid Pattern --}}
                            <defs>
                                <pattern id="illustration-grid" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <path d="M 20 0 L 0 0 0 20" fill="none" stroke="currentColor" stroke-width="1" class="text-slate-200/40 dark:text-slate-800/35" />
                                </pattern>
                            </defs>
                            <rect width="520" height="270" rx="8" fill="url(#illustration-grid)" />

                            {{-- ============================================================ --}}
                            {{-- LEFT SIDE: TRADITIONAL PHYSICAL QUEUE (THE PAIN)            --}}
                            {{-- ============================================================ --}}

                            {{-- Floor line for physical queue --}}
                            <path d="M 10 230 H 230" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" class="text-slate-300 dark:text-slate-700" />

                            {{-- Attendant / Cashier behind counter --}}
                            <g class="text-slate-400 dark:text-slate-500" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                {{-- Head --}}
                                <circle cx="22" cy="118" r="10" />
                                {{-- Torso --}}
                                <path d="M 22 128 L 22 180" />
                                {{-- Arms typing at terminal / checking papers --}}
                                <path d="M 22 140 L 36 150 L 44 150" />
                                {{-- Stool / Legs behind desk --}}
                                <path d="M 22 180 L 16 230" />
                                <path d="M 22 180 L 26 230" />
                            </g>

                            {{-- Cashier Desk / Counter & Computer Monitor --}}
                            <g class="text-slate-500 dark:text-slate-500">
                                {{-- Desk Body --}}
                                <rect x="36" y="152" width="24" height="78" rx="2" stroke="currentColor" stroke-width="2" class="fill-surface-subtle" />
                                {{-- POS Terminal / Computer Monitor on desk --}}
                                <rect x="42" y="134" width="12" height="15" rx="1.5" stroke="currentColor" stroke-width="1.5" class="fill-slate-200 dark:fill-slate-800" />
                                <line x1="48" y1="149" x2="48" y2="152" stroke="currentColor" stroke-width="1.5" />
                            </g>

                            {{-- Person 1 (Customer at counter waiting) --}}
                            <g class="text-slate-500 dark:text-slate-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                {{-- Head --}}
                                <circle cx="82" cy="115" r="11" />
                                {{-- Slumped Torso --}}
                                <path d="M 82 126 C 80 145, 80 165, 82 185" />
                                {{-- Arms leaning towards desk impatiently --}}
                                <path d="M 82 140 L 68 152 L 60 152" />
                                <path d="M 82 140 L 88 158 L 80 164" />
                                {{-- Legs --}}
                                <path d="M 82 185 L 76 230" />
                                <path d="M 82 185 L 88 230" />
                            </g>

                            {{-- Person 2 (Middle of queue - waiting tired) --}}
                            <g class="text-slate-400 dark:text-slate-500" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                {{-- Head --}}
                                <circle cx="135" cy="116" r="11" />
                                {{-- Torso --}}
                                <path d="M 135 127 L 135 185" />
                                {{-- Drooped arms --}}
                                <path d="M 135 140 L 126 166" />
                                <path d="M 135 140 L 144 166" />
                                {{-- Legs --}}
                                <path d="M 135 185 L 128 230" />
                                <path d="M 135 185 L 142 230" />
                            </g>

                            {{-- Person 3 (End of queue - worried / hands on head in despair) --}}
                            <g class="text-slate-400 dark:text-slate-500" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                {{-- Head --}}
                                <circle cx="188" cy="110" r="11" />
                                {{-- Torso --}}
                                <path d="M 188 121 L 188 185" />
                                {{-- Arms holding head in despair --}}
                                <path d="M 188 136 L 175 122 L 179 112" />
                                <path d="M 188 136 L 201 122 L 197 112" />
                                {{-- Legs --}}
                                <path d="M 188 185 L 181 230" />
                                <path d="M 188 185 L 195 230" />
                            </g>

                            {{-- Warning Tag above physical line (Pointing directly to the worried person) --}}
                            <g class="font-mono">
                                <text x="130" y="52" text-anchor="middle" class="fill-rose-600 dark:fill-rose-400 text-xs font-semibold tracking-tight">
                                    {{ __('Fila física: ~40 min') }}
                                </text>
                                {{-- Curved warning pointer leading directly to the worried person at the end of the line --}}
                                <path d="M 140 62 C 145 76, 188 74, 188 86" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 3" class="text-rose-500/60" />
                                {{-- Arrowhead pointing straight down to his head --}}
                                <path d="M 184 81 L 188 87 L 192 81" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-rose-500/80" />
                            </g>

                            {{-- ============================================================ --}}
                            {{-- CENTER DIVIDER: VS INDICATOR                                 --}}
                            {{-- ============================================================ --}}
                            <line x1="260" y1="30" x2="260" y2="240" stroke="currentColor" stroke-width="1" stroke-dasharray="4 6" class="text-slate-300 dark:text-slate-700" />
                            <circle cx="260" cy="135" r="14" class="fill-surface-canvas stroke-border-strong" stroke-width="1" />
                            <text x="260" y="139" text-anchor="middle" class="fill-slate-500 dark:fill-slate-400 font-mono text-xs font-bold">
                                VS
                            </text>

                            {{-- ============================================================ --}}
                            {{-- RIGHT SIDE: TURNEX USER (RELAXED & IN CONTROL)              --}}
                            {{-- ============================================================ --}}

                            {{-- Modern subtle floor aura for Turnex user --}}
                            <ellipse cx="395" cy="230" rx="72" ry="8" class="fill-blue-500/10 dark:fill-blue-500/15" />
                            <path d="M 315 230 H 475" stroke="currentColor" stroke-width="1.5" class="text-blue-500/30" />

                            {{-- Cozy Modern Armchair / Sofa --}}
                            {{-- Chair Legs --}}
                            <line x1="365" y1="196" x2="358" y2="230" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="text-blue-500/40 dark:text-blue-400/30" />
                            <line x1="425" y1="196" x2="435" y2="230" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="text-blue-500/40 dark:text-blue-400/30" />

                            {{-- Backrest Cushion (Ergonomic curve behind person) --}}
                            <path d="M 416 186 C 418 152, 426 130, 436 118 C 444 112, 452 118, 448 126 C 440 140, 432 162, 428 186 Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" class="fill-blue-500/5 dark:fill-blue-500/15 text-blue-500/40 dark:text-blue-400/40" />

                            {{-- Plush Seat Cushion --}}
                            <rect x="344" y="178" width="86" height="18" rx="6" stroke="currentColor" stroke-width="1.8" class="fill-blue-500/10 dark:fill-blue-500/20 text-blue-500/50 dark:text-blue-400/50" />

                            {{-- Relaxed Person sitting comfortably with crossed legs --}}
                            <g class="text-blue-600 dark:text-blue-400" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                {{-- Head resting back --}}
                                <circle cx="412" cy="106" r="11" class="fill-blue-500/10" />

                                {{-- Torso reclining into cushion --}}
                                <path d="M 410 117 L 396 178" />

                                {{-- Lower supporting leg to floor --}}
                                <path d="M 396 178 L 348 178 L 344 230" />

                                {{-- Casual crossed leg hanging over knee --}}
                                <path d="M 396 178 L 346 166 L 332 198" />

                                {{-- Arms holding smartphone --}}
                                <path d="M 406 128 L 382 152 L 362 142" />
                                <path d="M 406 128 L 374 144 L 362 142" />
                            </g>

                            {{-- Armchair Armrest in foreground --}}
                            <rect x="350" y="156" width="72" height="12" rx="6" stroke="currentColor" stroke-width="1.8" class="fill-surface-canvas text-blue-500/60 dark:text-blue-400/50" />

                            {{-- Glowing Smartphone in hands --}}
                            <rect x="350" y="128" width="15" height="25" rx="3" class="fill-blue-600 dark:fill-blue-500 stroke-white dark:stroke-slate-900" stroke-width="1.5" />
                            <line x1="354" y1="132" x2="361" y2="132" stroke="white" stroke-width="1" stroke-linecap="round" />

                            {{-- Floating Digital Notification (Centered horizontally over Turnex user) --}}
                            <g class="font-mono">
                                {{-- Connecting signal ray to phone --}}
                                <path d="M 395 72 C 395 95, 358 100, 358 124" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3" class="text-blue-500/50" />

                                {{-- Ticket Notification Text in Geist Mono --}}
                                <text x="395" y="48" text-anchor="middle" class="fill-blue-600 dark:fill-blue-400 text-xs font-bold tracking-tight">
                                    {{ __('TURNO #A-042') }}
                                </text>
                                <text x="395" y="62" text-anchor="middle" class="fill-slate-600 dark:fill-slate-300 text-xs tracking-normal">
                                    {{ __('Faltan ~15 min · Ve acercándote') }}
                                </text>
                            </g>
                        </svg>

                        {{-- Comparative List (Desventajas vs. Ventajas) --}}
                        <div class="grid grid-cols-2 gap-4 sm:gap-6 pt-1">
                            {{-- Left List: Desventajas de la fila tradicional --}}
                            <div class="flex flex-col gap-2">
                                <span class="text-xs font-mono uppercase tracking-wider font-semibold text-rose-600 dark:text-rose-400">
                                    {{ __('Fila Tradicional') }}
                                </span>
                                <ul class="flex flex-col gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-rose-500 font-bold shrink-0">✕</span>
                                        <span>{{ __('30 a 60 min de pie sin avanzar.') }}</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-rose-500 font-bold shrink-0">✕</span>
                                        <span>{{ __('Incertidumbre total sobre tu turno.') }}</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-rose-500 font-bold shrink-0">✕</span>
                                        <span>{{ __('Congestión y estrés en la sala.') }}</span>
                                    </li>
                                </ul>
                            </div>

                            {{-- Right List: Ventajas con Turnex --}}
                            <div class="flex flex-col gap-2">
                                <span class="text-xs font-mono uppercase tracking-wider font-semibold text-blue-600 dark:text-blue-400">
                                    {{ __('Con Turnex') }}
                                </span>
                                <ul class="flex flex-col gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-emerald-500 dark:text-emerald-400 font-bold shrink-0">✓</span>
                                        <span>{{ __('Espera libre y productiva donde quieras.') }}</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-emerald-500 dark:text-emerald-400 font-bold shrink-0">✓</span>
                                        <span>{{ __('Alerta a tu móvil cuando sea tu momento.') }}</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-emerald-500 dark:text-emerald-400 font-bold shrink-0">✓</span>
                                        <span>{{ __('Atención ágil y orden en tu negocio.') }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        {{-- Footer (Full Width) --}}
        <footer class="w-full px-6 sm:px-8 lg:px-12 py-5 border-t border-border-subtle flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-mono text-slate-500 dark:text-slate-500">
            <div>
                © {{ date('Y') }} Turnex — {{ __('Gestión virtual de turnos y autoservicio.') }}
            </div>

            <div class="flex items-center gap-4">
                <a href="{{ route('business.login') }}" class="hover:text-slate-900 dark:hover:text-slate-300 transition-colors">
                    {{ __('Portal Empresas') }}
                </a>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    <span>{{ config('app.version') }}</span>
                </span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
