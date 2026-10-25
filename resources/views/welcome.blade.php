<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen flex flex-col justify-between bg-[#F8FAFC] text-[#0F172A] antialiased dark:bg-[#0B0F17] dark:text-[#F9FAFB] selection:bg-blue-600 selection:text-white font-sans">
        {{-- Top Navigation Header (Full Width) --}}
        <header class="w-full px-6 sm:px-8 lg:px-12 py-5 flex items-center justify-between">
            {{-- Turnex Brand Logo --}}
            <a href="{{ route('home') }}" class="group inline-flex flex-col focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 rounded-md p-1">
                <span class="font-black tracking-tight text-xl sm:text-2xl text-slate-900 dark:text-slate-100 leading-none">
                    TURN<span class="text-blue-600 dark:text-blue-500">EX</span>
                </span>
                <span class="font-mono text-[9px] sm:text-[10px] tracking-[0.25em] text-slate-500 dark:text-slate-400 uppercase mt-1">
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
                    class="inline-flex items-center justify-center size-9 rounded-md border border-[#E2E8F0] dark:border-[#1F2937] bg-white dark:bg-[#111827] text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-700 transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 cursor-pointer"
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

                @auth('customer')
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
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center h-9 px-3 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 rounded-md"
                    >
                        {{ __('Iniciar sesión') }}
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center justify-center h-9 px-3.5 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 shadow-xs"
                    >
                        {{ __('Registrarse') }}
                    </a>
                @endauth
            </div>
        </header>

        {{-- Main Hero Section (Split Layout: Text Left / Line-Art Illustration Right) --}}
        <main class="w-full px-6 sm:px-8 lg:px-12 pt-4 sm:pt-6 lg:pt-8 pb-12 flex-1 flex flex-col justify-start">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-8 xl:gap-10 items-center w-full">
                {{-- Left Column: Value Proposition & CTAs --}}
                <div class="lg:col-span-7 xl:col-span-6 flex flex-col items-start gap-5 sm:gap-6">
                    {{-- Main Headline --}}
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 dark:text-slate-100 leading-[1.12]">
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

                        <div class="inline-flex items-center gap-2 h-11 px-4 text-sm font-medium rounded-md border border-[#CBD5E1] dark:border-[#374151] bg-white/60 dark:bg-[#111827]/60 text-slate-700 dark:text-slate-300 w-full sm:w-auto justify-between sm:justify-center">
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>{{ __('Soy un Negocio') }}</span>
                            </span>
                            <span class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-wider rounded-sm bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400">
                                {{ __('Próximamente') }}
                            </span>
                        </div>
                    </div>

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
                            <span>{{ __('Actualización en tiempo real') }}</span>
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
                                <rect x="36" y="152" width="24" height="78" rx="2" stroke="currentColor" stroke-width="2" class="fill-[#F1F5F9] dark:fill-[#1F2937]" />
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
                                <text x="130" y="52" text-anchor="middle" class="fill-rose-600 dark:fill-rose-400 text-[12px] font-semibold tracking-tight">
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
                            <circle cx="260" cy="135" r="14" class="fill-white dark:fill-[#0B0F17] stroke-[#CBD5E1] dark:stroke-[#374151]" stroke-width="1" />
                            <text x="260" y="139" text-anchor="middle" class="fill-slate-500 dark:fill-slate-400 font-mono text-[10px] font-bold">
                                VS
                            </text>

                            {{-- ============================================================ --}}
                            {{-- RIGHT SIDE: TURNEX USER (RELAXED & IN CONTROL)              --}}
                            {{-- ============================================================ --}}

                            {{-- Modern subtle floor aura for Turnex user --}}
                            <ellipse cx="395" cy="230" rx="55" ry="8" class="fill-blue-500/10 dark:fill-blue-500/15" />
                            <path d="M 330 230 H 460" stroke="currentColor" stroke-width="1.5" class="text-blue-500/30" />

                            {{-- Relaxed Person using phone --}}
                            <g class="text-blue-600 dark:text-blue-400" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                {{-- Head with proud/upright posture --}}
                                <circle cx="395" cy="105" r="12" class="fill-blue-500/10" />
                                {{-- Upright Torso --}}
                                <path d="M 395 117 L 395 180" />
                                {{-- Arms holding smartphone in front --}}
                                <path d="M 395 132 L 415 146 L 410 156" />
                                <path d="M 395 132 L 382 148 L 402 156" />
                                {{-- Confident relaxed legs --}}
                                <path d="M 395 180 L 384 230" />
                                <path d="M 395 180 L 406 230" />
                            </g>

                            {{-- Glowing Smartphone in hands --}}
                            <rect x="404" y="142" width="16" height="26" rx="3" class="fill-blue-600 dark:fill-blue-500 stroke-white dark:stroke-slate-900" stroke-width="1.5" />
                            <line x1="409" y1="146" x2="415" y2="146" stroke="white" stroke-width="1" stroke-linecap="round" />

                            {{-- Floating Digital Notification (Centered horizontally over Turnex user) --}}
                            <g class="font-mono">
                                {{-- Connecting signal ray to phone --}}
                                <path d="M 395 72 C 395 95, 412 112, 412 138" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 3" class="text-blue-500/50" />

                                {{-- Ticket Notification Text in Geist Mono --}}
                                <text x="395" y="48" text-anchor="middle" class="fill-blue-600 dark:fill-blue-400 text-[12px] font-bold tracking-tight">
                                    {{ __('TURNO #A-042') }}
                                </text>
                                <text x="395" y="62" text-anchor="middle" class="fill-slate-600 dark:fill-slate-300 text-[10px] tracking-normal">
                                    {{ __('¡Es tu momento, acércate!') }}
                                </text>
                            </g>
                        </svg>

                        {{-- Comparative List (Desventajas vs. Ventajas) --}}
                        <div class="grid grid-cols-2 gap-4 sm:gap-6 pt-1">
                            {{-- Left List: Desventajas de la fila tradicional --}}
                            <div class="flex flex-col gap-2">
                                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold text-rose-600 dark:text-rose-400">
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
                                <span class="text-[11px] font-mono uppercase tracking-wider font-semibold text-blue-600 dark:text-blue-400">
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
                                        <span>{{ __('Llegada puntual y atención ágil.') }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        {{-- Footer (Full Width) --}}
        <footer class="w-full px-6 sm:px-8 lg:px-12 py-5 border-t border-[#E2E8F0] dark:border-[#1F2937] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-mono text-slate-500 dark:text-slate-500">
            <div>
                © {{ date('Y') }} Turnex — {{ __('Gestión virtual de turnos y autoservicio.') }}
            </div>

            <div class="flex items-center gap-4">
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    <span>{{ config('app.version') }}</span>
                </span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
