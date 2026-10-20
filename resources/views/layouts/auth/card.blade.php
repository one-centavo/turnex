<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased dark:bg-[#0B0F17] dark:text-[#F9FAFB] selection:bg-blue-600 selection:text-white font-sans">
        <div class="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 md:p-8 pb-28 sm:pb-10">
            <div class="flex w-full max-w-md flex-col gap-5">
                {{-- Turnex Brand Header --}}
                <div class="flex justify-center">
                    <a href="{{ route('home') }}" class="group inline-flex flex-col items-center focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600 rounded-md p-1" wire:navigate>
                        <span class="font-black tracking-tight text-2xl text-slate-900 dark:text-slate-100 leading-none">
                            TURN<span class="text-blue-600 dark:text-blue-500">EX</span>
                        </span>
                        <span class="font-mono text-[10px] tracking-[0.25em] text-slate-500 dark:text-slate-400 uppercase mt-1">
                            Queue Platform
                        </span>
                    </a>
                </div>

                {{-- Card Container conforming to Design System: 1px border, rounded-lg, flat surface --}}
                <main class="w-full rounded-lg border border-[#E2E8F0] bg-white p-5 sm:p-7 dark:border-[#1F2937] dark:bg-[#111827]">
                    {{ $slot }}
                </main>

                {{-- Subdued Industrial Footer --}}
                <footer class="flex items-center justify-between px-1 text-xs text-slate-500 dark:text-slate-500 font-mono">
                    <span>© {{ date('Y') }} Turnex</span>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-1">
                            <span class="size-1 rounded-full bg-emerald-500"></span>
                            <span>{{ config('app.version') }}</span>
                        </span>
                    </div>
                </footer>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
