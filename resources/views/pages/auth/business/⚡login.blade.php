<?php

declare(strict_types=1);

use App\Services\BusinessAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Iniciar sesión — Negocios')] #[Layout('layouts.auth.card')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('business')->check() || Auth::guard('web')->check()) {
            $this->redirectIntended(default: route('dashboard'), navigate: true);

            return;
        }
    }

    public function login(BusinessAuthService $authService): void
    {
        $this->validate(
            [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ],
            [
                'email.required' => __('El correo electrónico es obligatorio.'),
                'email.email' => __('Ingresa un correo electrónico válido.'),
                'password.required' => __('La contraseña es obligatoria.'),
            ],
        );

        try {
            $authService->authenticate(email: $this->email, password: $this->password, remember: $this->remember);
        } catch (ValidationException $e) {
            $this->password = '';

            throw $e;
        }

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
};
?>

<div class="flex flex-col gap-5">
    {{-- Header conforming to Design System --}}
    <header class="flex flex-col gap-1.5 mb-1">
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-[#0F172A] dark:text-[#F9FAFB]">
            {{ __('Inicia sesión en tu negocio') }}
        </h1>
        <p class="text-xs sm:text-sm text-[#64748B] dark:text-[#9CA3AF]">
            {{ __('Ingresa las credenciales operativas de tu establecimiento.') }}
        </p>
    </header>

    {{-- System / General Alert Banner --}}
    @error('general')
        <div class="flex items-start gap-2.5 rounded-md border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-600 dark:text-red-400"
            role="alert">
            <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="leading-relaxed font-medium">{{ $message }}</span>
        </div>
    @enderror

    @if (session('status'))
        <div class="flex items-start gap-2.5 rounded-md border border-emerald-500/25 bg-emerald-500/10 p-3 text-xs text-emerald-600 dark:text-emerald-400"
            role="status">
            <span class="leading-relaxed font-medium">{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="flex items-start gap-2.5 rounded-md border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-600 dark:text-red-400"
            role="alert">
            <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="leading-relaxed font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Business Login Form --}}
    <form wire:submit="login" class="flex flex-col gap-5">
        {{-- Email Address --}}
        <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" required
            autofocus autocomplete="email" placeholder="contacto@tuempresa.com" />

        {{-- Password with Eye Icon Toggle --}}
        <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
                <label for="password" class="text-xs sm:text-sm font-medium text-[#64748B] dark:text-[#9CA3AF]">
                    {{ __('Contraseña') }}
                </label>
                <a href="{{ route('password.request') }}"
                    class="text-xs sm:text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                    wire:navigate>
                    {{ __('¿Olvidaste tu contraseña?') }}
                </a>
            </div>

            <div class="relative flex items-center" x-data="{ showPassword: false }">
                <input id="password" wire:model="password" :type="showPassword ? 'text' : 'password'" required
                    autocomplete="current-password" placeholder="••••••••"
                    class="h-10 w-full rounded-md border px-3 pr-10 text-sm transition-colors bg-white text-[#0F172A] placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:bg-[#0B0F17] dark:text-[#F9FAFB] dark:placeholder:text-slate-500 {{ $errors->has('password') ? 'border-red-500' : 'border-[#CBD5E1] dark:border-[#374151]' }}" />
                <button type="button" @click="showPassword = !showPassword"
                    class="absolute right-0 top-0 bottom-0 flex items-center justify-center w-10 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                    :aria-label="showPassword ? '{{ __('Ocultar contraseña') }}' : '{{ __('Mostrar contraseña') }}'">
                    <flux:icon name="eye" x-show="!showPassword" class="size-4.5" />
                    <flux:icon name="eye-slash" x-show="showPassword" x-cloak class="size-4.5" />
                </button>
            </div>
            @error('password')
                <p class="text-xs text-red-500 font-medium">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me Checkbox --}}
        <flux:checkbox wire:model="remember" :label="__('Recordarme en este dispositivo')" />

        {{-- Primary Submit Button --}}
        <flux:button variant="primary" type="submit" class="w-full h-11 text-base cursor-pointer">
            <span wire:loading.remove wire:target="login">
                {{ __('Iniciar sesión') }}
            </span>
            <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span>{{ __('Iniciando sesión...') }}</span>
            </span>
        </flux:button>
    </form>

    {{-- Business Registration Link --}}
    <div class="pt-3 text-center text-xs sm:text-sm text-[#64748B] dark:text-[#9CA3AF] border-t border-[#E2E8F0] dark:border-[#1F2937] flex flex-col gap-2">
        <div>
            {{ __('¿Aún no has registrado tu empresa?') }}
            <a href="{{ route('business.register') }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                wire:navigate>
                {{ __('Registra tu negocio') }}
            </a>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
            {{ __('¿Eres un cliente buscando turnos o pedidos?') }}
            <a href="{{ route('login') }}" class="font-medium text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 underline transition-colors"
                wire:navigate>
                {{ __('Inicia sesión aquí') }}
            </a>
        </div>
    </div>
</div>
