<?php

declare(strict_types=1);

use App\Enums\BusinessCategory;
use App\Enums\ContactType;
use App\Exceptions\OtpCooldownException;
use App\Rules\BusinessRules;
use App\Services\BusinessService;
use App\Services\OtpService;
use App\Support\DataNormalizer;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrar mi negocio')] #[Layout('layouts.auth.card')] class extends Component
{
    public int $step = 1;

    // Step 1 Form fields
    public string $business_name = '';

    public string $business_category = '';

    public string $owner_first_name = '';

    public string $owner_middle_name = '';

    public string $owner_last_name = '';

    public string $owner_second_last_name = '';

    public string $location = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    // Step 2 Verification fields
    public string $code = '';

    public int $emailEditCount = 0;

    public bool $isEditingEmail = false;

    public string $new_email = '';

    public int $cooldownSeconds = 0;

    public string $maskedEmail = '';

    public function mount(): void
    {
        if (Auth::guard('business')->check() || Auth::guard('web')->check()) {
            $this->redirectIntended(default: route('dashboard'), navigate: true);

            return;
        }

        /** @var array{
         *     step?: int,
         *     business_name?: string,
         *     business_category?: string,
         *     owner_first_name?: string,
         *     owner_middle_name?: string|null,
         *     owner_last_name?: string,
         *     owner_second_last_name?: string|null,
         *     location?: string,
         *     email?: string,
         *     email_edit_count?: int,
         *     cooldown_until?: string,
         *     expires_at?: string
         * }|null $sessionData */
        $sessionData = Session::get('business_registration');

        if ($sessionData !== null) {
            if (isset($sessionData['expires_at']) && Carbon::parse($sessionData['expires_at'])->isPast()) {
                Session::forget('business_registration');
                $this->step = 1;

                return;
            }

            if (($sessionData['step'] ?? 1) === 2 && ! empty($sessionData['email'])) {
                $this->step = 2;
                $this->business_name = $sessionData['business_name'] ?? '';
                $this->business_category = $sessionData['business_category'] ?? '';
                $this->owner_first_name = $sessionData['owner_first_name'] ?? '';
                $this->owner_middle_name = $sessionData['owner_middle_name'] ?? '';
                $this->owner_last_name = $sessionData['owner_last_name'] ?? '';
                $this->owner_second_last_name = $sessionData['owner_second_last_name'] ?? '';
                $this->location = $sessionData['location'] ?? '';
                $this->email = $sessionData['email'];
                $this->emailEditCount = $sessionData['email_edit_count'] ?? 0;
                $this->maskedEmail = DataNormalizer::maskEmail($this->email);
                $this->syncCooldown();
            }
        }
    }

    public function submitStepOne(OtpService $otpService): void
    {
        $ip = request()->ip() ?? '127.0.0.1';
        $rateKey = 'business_reg_ip:'.$ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->addError('general', "Has realizado demasiados intentos. Por favor espera {$seconds} segundos antes de intentar nuevamente.");

            return;
        }

        $this->business_name = DataNormalizer::name($this->business_name);
        $this->owner_first_name = DataNormalizer::name($this->owner_first_name);
        $this->owner_middle_name = filled($this->owner_middle_name) ? DataNormalizer::name($this->owner_middle_name) : '';
        $this->owner_last_name = DataNormalizer::name($this->owner_last_name);
        $this->owner_second_last_name = filled($this->owner_second_last_name) ? DataNormalizer::name($this->owner_second_last_name) : '';
        $this->location = DataNormalizer::name($this->location);
        $this->email = DataNormalizer::email($this->email);

        $this->validate([
            'business_name' => BusinessRules::businessName(),
            'business_category' => BusinessRules::category(),
            'location' => BusinessRules::location(),
            'owner_first_name' => BusinessRules::ownerFirstName(),
            'owner_middle_name' => BusinessRules::ownerMiddleName(),
            'owner_last_name' => BusinessRules::ownerLastName(),
            'owner_second_last_name' => BusinessRules::ownerSecondLastName(),
            'email' => BusinessRules::email(),
            'password' => BusinessRules::password(confirmed: true),
            'terms' => BusinessRules::terms(),
        ], BusinessRules::messages());

        RateLimiter::hit($rateKey, 60);

        $normalizedEmail = $this->email;
        $normalizedBusinessName = $this->business_name;
        $normalizedCategory = $this->business_category;
        $normalizedLocation = $this->location;
        $normalizedOwnerFirstName = $this->owner_first_name;
        $normalizedOwnerMiddleName = filled($this->owner_middle_name) ? $this->owner_middle_name : null;
        $normalizedOwnerLastName = $this->owner_last_name;
        $normalizedOwnerSecondLastName = filled($this->owner_second_last_name) ? $this->owner_second_last_name : null;

        try {
            $otpService->generate($normalizedEmail, ContactType::Email);
        } catch (OtpCooldownException $e) {
            $this->cooldownSeconds = $e->remainingSeconds;
        } catch (\Throwable $e) {
            $this->addError('general', 'No fue posible despachar el código de verificación por correo en este momento. Intenta nuevamente.');

            return;
        }

        $cooldownUntil = now()->addSeconds($this->cooldownSeconds > 0 ? $this->cooldownSeconds : OtpService::COOLDOWN_SECONDS);

        Session::put('business_registration', [
            'step' => 2,
            'business_name' => $normalizedBusinessName,
            'business_category' => $normalizedCategory,
            'owner_first_name' => $normalizedOwnerFirstName,
            'owner_middle_name' => $normalizedOwnerMiddleName,
            'owner_last_name' => $normalizedOwnerLastName,
            'owner_second_last_name' => $normalizedOwnerSecondLastName,
            'location' => $normalizedLocation,
            'email' => $normalizedEmail,
            'password' => $this->password,
            'email_edit_count' => 0,
            'cooldown_until' => $cooldownUntil->toIso8601String(),
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);

        $this->step = 2;
        $this->maskedEmail = DataNormalizer::maskEmail($normalizedEmail);
        $this->cooldownSeconds = max(0, (int) now()->diffInSeconds($cooldownUntil, false));
        $this->code = '';

        Flux::toast(variant: 'success', text: __('Código de verificación enviado a tu correo corporativo o personal.'));
    }

    public function verifyCode(BusinessService $businessService, OtpService $otpService): void
    {
        $this->resetErrorBag('code');

        if ($this->hasSessionExpired()) {
            $this->handleExpiredSession();

            return;
        }

        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Ingresa el código de 6 dígitos.',
            'code.size' => 'El código debe tener exactamente 6 dígitos.',
        ]);

        /** @var array{
         *     business_name: string,
         *     business_category: string,
         *     owner_first_name: string,
         *     owner_middle_name: string|null,
         *     owner_last_name: string,
         *     owner_second_last_name: string|null,
         *     location: string,
         *     email: string,
         *     password: string
         * } $sessionData */
        $sessionData = Session::get('business_registration');

        $isValid = $otpService->verify($sessionData['email'], $this->code, ContactType::Email);

        if (! $isValid) {
            $this->addError('code', 'El código ingresado es incorrecto o ha expirado.');

            return;
        }

        $business = $businessService->register([
            'business_name' => $sessionData['business_name'],
            'business_category' => $sessionData['business_category'],
            'owner_first_name' => $sessionData['owner_first_name'],
            'owner_middle_name' => $sessionData['owner_middle_name'] ?? null,
            'owner_last_name' => $sessionData['owner_last_name'],
            'owner_second_last_name' => $sessionData['owner_second_last_name'] ?? null,
            'email' => $sessionData['email'],
            'location' => $sessionData['location'],
            'password' => $sessionData['password'],
        ]);

        Session::forget('business_registration');

        Auth::guard('business')->login($business);
        Session::regenerate();

        $this->business_name = $business->business_name;
        $this->email = $business->email;
        $this->maskedEmail = DataNormalizer::maskEmail($business->email);
        $this->step = 3;
    }

    public function finishRegistration(): void
    {
        $this->redirectIntended(default: route('dashboard', ['setup' => 1]), navigate: true);
    }

    public function resendOtp(OtpService $otpService): void
    {
        $this->resetErrorBag('code');

        if ($this->hasSessionExpired()) {
            $this->handleExpiredSession();

            return;
        }

        /** @var array{
         *     step: int,
         *     business_name: string,
         *     business_category: string,
         *     owner_first_name: string,
         *     owner_middle_name: string|null,
         *     owner_last_name: string,
         *     owner_second_last_name: string|null,
         *     location: string,
         *     email: string,
         *     password: string,
         *     email_edit_count: int,
         *     cooldown_until: string,
         *     expires_at: string
         * } $sessionData */
        $sessionData = Session::get('business_registration');

        $this->syncCooldown();

        if ($this->cooldownSeconds > 0) {
            $this->addError('code', "Debes esperar {$this->cooldownSeconds} segundos antes de solicitar otro código.");

            return;
        }

        try {
            $otpService->generate($sessionData['email'], ContactType::Email);
            $newCooldownUntil = now()->addSeconds(OtpService::COOLDOWN_SECONDS);
            $sessionData['cooldown_until'] = $newCooldownUntil->toIso8601String();
            Session::put('business_registration', $sessionData);
            $this->cooldownSeconds = OtpService::COOLDOWN_SECONDS;

            Flux::toast(variant: 'success', text: __('Nuevo código de verificación enviado a tu correo.'));
        } catch (OtpCooldownException $e) {
            $this->cooldownSeconds = $e->remainingSeconds;
            $this->addError('code', "Espera {$this->cooldownSeconds} segundos antes de volver a solicitar un código.");
        } catch (\Throwable $e) {
            $this->addError('code', 'Ocurrió un error al despachar el código. Intenta de nuevo.');
        }
    }

    public function editEmail(): void
    {
        if ($this->emailEditCount >= 3) {
            $this->addError('email_edit', 'Has alcanzado el límite máximo de 3 cambios de correo electrónico.');

            return;
        }

        $this->new_email = $this->email;
        $this->isEditingEmail = true;
    }

    public function cancelEditEmail(): void
    {
        $this->isEditingEmail = false;
        $this->new_email = '';
        $this->resetErrorBag(['new_email', 'email_edit']);
    }

    public function saveNewEmail(OtpService $otpService): void
    {
        if ($this->hasSessionExpired()) {
            $this->handleExpiredSession();

            return;
        }

        /** @var array{
         *     step: int,
         *     business_name: string,
         *     business_category: string,
         *     owner_first_name: string,
         *     owner_middle_name: string|null,
         *     owner_last_name: string,
         *     owner_second_last_name: string|null,
         *     location: string,
         *     email: string,
         *     password: string,
         *     email_edit_count: int,
         *     cooldown_until: string,
         *     expires_at: string
         * } $sessionData */
        $sessionData = Session::get('business_registration');

        if ($sessionData['email_edit_count'] >= 3) {
            $this->addError('new_email', 'Has alcanzado el límite de 3 cambios de correo. Debes reiniciar el registro.');

            return;
        }

        $this->validate([
            'new_email' => BusinessRules::email(),
        ], BusinessRules::messages());

        $normalized = DataNormalizer::email($this->new_email);

        // Submitting an unchanged email does not decrement the remaining edit attempts
        if ($normalized === $sessionData['email']) {
            $this->isEditingEmail = false;
            $this->new_email = '';

            return;
        }

        $newEditCount = $sessionData['email_edit_count'] + 1;
        $sessionData['email_edit_count'] = $newEditCount;
        $sessionData['email'] = $normalized;

        try {
            $otpService->generate($normalized, ContactType::Email);
            $sessionData['cooldown_until'] = now()->addSeconds(OtpService::COOLDOWN_SECONDS)->toIso8601String();
            $this->cooldownSeconds = OtpService::COOLDOWN_SECONDS;
        } catch (OtpCooldownException $e) {
            $this->cooldownSeconds = $e->remainingSeconds;
        } catch (\Throwable $e) {
            // Keep going, user can request resend
        }

        Session::put('business_registration', $sessionData);

        $this->email = $normalized;
        $this->emailEditCount = $newEditCount;
        $this->maskedEmail = DataNormalizer::maskEmail($normalized);
        $this->isEditingEmail = false;
        $this->code = '';

        Flux::toast(variant: 'success', text: __('Correo electrónico actualizado y nuevo código enviado.'));
    }

    public function decrementCooldown(): void
    {
        if ($this->cooldownSeconds > 0) {
            $this->cooldownSeconds--;
        }
    }

    public function goToStepOne(): void
    {
        $this->step = 1;
    }

    private function syncCooldown(): void
    {
        $sessionData = Session::get('business_registration');
        if (is_array($sessionData) && isset($sessionData['cooldown_until'])) {
            $cooldownUntil = Carbon::parse($sessionData['cooldown_until']);
            $this->cooldownSeconds = now()->isBefore($cooldownUntil)
                ? (int) now()->diffInSeconds($cooldownUntil)
                : 0;
        } else {
            $this->cooldownSeconds = 0;
        }
    }

    private function hasSessionExpired(): bool
    {
        $sessionData = Session::get('business_registration');

        if (! is_array($sessionData) || ! isset($sessionData['expires_at'])) {
            return true;
        }

        return Carbon::parse($sessionData['expires_at'])->isPast();
    }

    private function handleExpiredSession(): void
    {
        Session::forget('business_registration');
        $this->step = 1;
        $this->reset(['code', 'emailEditCount', 'isEditingEmail']);
        $this->addError('general', 'Tu sesión ha expirado tras 15 minutos de inactividad. Por favor, completa tus datos nuevamente.');
        Flux::toast(variant: 'warning', text: __('Sesión expirada. Por favor reinicia tu registro.'));
    }
};
?>

<div class="flex flex-col gap-5" @if($step === 2 && $cooldownSeconds > 0) wire:poll.1s="decrementCooldown" @endif>
    @if ($step < 3)
        {{-- Operational Stepper Indicator --}}
        <nav aria-label="Progreso de registro de negocio" class="flex items-center justify-between pb-4 border-b border-border-subtle">
            {{-- Step 1 Indicator --}}
            <div class="flex items-center gap-2">
                <span class="flex size-5 items-center justify-center rounded-sm font-mono text-xs font-bold transition-colors {{ $step === 1 ? 'bg-blue-600 text-white' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' }}">
                    @if ($step > 1)
                        ✓
                    @else
                        1
                    @endif
                </span>
                <span class="text-xs font-semibold tracking-wider uppercase {{ $step === 1 ? 'text-text-primary' : 'text-emerald-600 dark:text-emerald-400' }}">
                    01 · Negocio
                </span>
            </div>

            {{-- Divider Track --}}
            <div class="h-px flex-1 mx-3 bg-border-subtle"></div>

            {{-- Step 2 Indicator --}}
            <div class="flex items-center gap-2">
                <span class="flex size-5 items-center justify-center rounded-sm font-mono text-xs font-bold transition-colors {{ $step === 2 ? 'bg-blue-600 text-white' : 'bg-surface-subtle text-slate-600 dark:text-slate-400 border border-border-subtle' }}">
                    2
                </span>
                <span class="text-xs font-semibold tracking-wider uppercase {{ $step === 2 ? 'text-text-primary' : 'text-slate-600 dark:text-slate-400' }}">
                    02 · Verificación
                </span>
            </div>
        </nav>
    @endif

    {{-- System / Rate Limiting Alert --}}
    @error('general')
        <div class="flex items-start gap-2.5 rounded-md border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-600 dark:text-red-400" role="alert">
            <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="leading-relaxed font-medium">{{ $message }}</span>
        </div>
    @enderror

    @if ($step === 1)
        {{-- STEP 1: BUSINESS & OWNER INFORMATION --}}
        <header class="flex flex-col gap-1.5 mb-1">
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-text-primary">
                {{ __('Registra tu negocio') }}
            </h1>
            <p class="text-xs sm:text-sm text-text-secondary">
                {{ __('Gestiona tus filas virtuales, pedidos y catálogos en tiempo real.') }}
            </p>
        </header>

        <form wire:submit="submitStepOne" class="flex flex-col gap-5">
            {{-- Business Name --}}
            <div class="flex flex-col">
                <label for="business_name" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                    {{ __('Nombre comercial del negocio') }} <span class="text-red-500">*</span>
                </label>
                <input
                    id="business_name"
                    wire:model="business_name"
                    type="text"
                    required
                    autofocus
                    autocomplete="organization"
                    placeholder="Ej. Café Don Pedro"
                    class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('business_name') ? 'border-red-500' : 'border-border-strong' }}"
                />
                @error('business_name')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Business Category --}}
            <div class="flex flex-col">
                <label for="business_category" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                    {{ __('Categoría del negocio') }} <span class="text-red-500">*</span>
                </label>
                <select
                    id="business_category"
                    wire:model="business_category"
                    required
                    class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent {{ $errors->has('business_category') ? 'border-red-500' : 'border-border-strong' }}"
                >
                    <option value="">{{ __('Selecciona una categoría...') }}</option>
                    @foreach (BusinessCategory::cases() as $category)
                        <option value="{{ $category->value }}">{{ $category->label() }}</option>
                    @endforeach
                </select>
                @error('business_category')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Owner Names: First Name & Middle Name --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col">
                    <label for="owner_first_name" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                        {{ __('Primer nombre') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="owner_first_name"
                        wire:model="owner_first_name"
                        type="text"
                        required
                        autocomplete="given-name"
                        placeholder="Ej. Carlos"
                        pattern="[a-zA-ZÀ-ÿ\s]+"
                        oninput="this.value = this.value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '')"
                        class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('owner_first_name') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    @error('owner_first_name')
                        <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col">
                    <label for="owner_middle_name" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                        {{ __('Segundo nombre') }} <span class="text-slate-400 font-normal">({{ __('opcional') }})</span>
                    </label>
                    <input
                        id="owner_middle_name"
                        wire:model="owner_middle_name"
                        type="text"
                        autocomplete="additional-name"
                        placeholder="Ej. Andrés"
                        pattern="[a-zA-ZÀ-ÿ\s]+"
                        oninput="this.value = this.value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '')"
                        class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('owner_middle_name') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    @error('owner_middle_name')
                        <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Owner Last Names: First Last Name & Second Last Name --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col">
                    <label for="owner_last_name" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                        {{ __('Primer apellido') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="owner_last_name"
                        wire:model="owner_last_name"
                        type="text"
                        required
                        autocomplete="family-name"
                        placeholder="Ej. Gómez"
                        pattern="[a-zA-ZÀ-ÿ\s]+"
                        oninput="this.value = this.value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '')"
                        class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('owner_last_name') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    @error('owner_last_name')
                        <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col">
                    <label for="owner_second_last_name" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                        {{ __('Segundo apellido') }} <span class="text-slate-400 font-normal">({{ __('opcional') }})</span>
                    </label>
                    <input
                        id="owner_second_last_name"
                        wire:model="owner_second_last_name"
                        type="text"
                        placeholder="Ej. Rodríguez"
                        pattern="[a-zA-ZÀ-ÿ\s]+"
                        oninput="this.value = this.value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '')"
                        class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('owner_second_last_name') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    @error('owner_second_last_name')
                        <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Physical Location / Address --}}
            <div class="flex flex-col">
                <label for="location" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                    {{ __('Dirección o ubicación del negocio') }} <span class="text-red-500">*</span>
                </label>
                <input
                    id="location"
                    wire:model="location"
                    type="text"
                    required
                    autocomplete="street-address"
                    placeholder="Ej. Carrera 15 # 85-30, Bogotá"
                    class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('location') ? 'border-red-500' : 'border-border-strong' }}"
                />
                @error('location')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email Address --}}
            <div class="flex flex-col">
                <label for="email" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                    {{ __('Correo electrónico corporativo o personal') }} <span class="text-red-500">*</span>
                </label>
                <input
                    id="email"
                    wire:model="email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="contacto@minegocio.com"
                    class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('email') ? 'border-red-500' : 'border-border-strong' }}"
                />
                <div class="flex items-center justify-between mt-1.5">
                    <p class="text-xs text-text-secondary">
                        {{ __('Te enviaremos un código de seguridad de 6 dígitos a este correo.') }}
                    </p>
                </div>
                @error('email')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password Field with Eye Icon Toggle & Focus Requirements --}}
            <div
                class="flex flex-col"
                x-data="{
                    showPassword: false,
                    isFocused: false,
                    password: '',
                    get hasLength() { return this.password.length >= 8 && this.password.length <= 64; },
                    get hasMixed() { return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password); },
                    get hasNumber() { return /[0-9]/.test(this.password); },
                    get hasSymbol() { return /[^A-Za-z0-9\s]/.test(this.password); },
                    get hasNoSpaces() { return !/\s/.test(this.password) && this.password.length > 0; }
                }"
            >
                <label for="password" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                    {{ __('Contraseña') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                    <input
                        id="password"
                        wire:model="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        maxlength="64"
                        autocomplete="new-password"
                        placeholder="De 8 a 64 caracteres"
                        @keydown.space.prevent
                        @input="password = $event.target.value = $event.target.value.replace(/\s/g, '')"
                        @focus="isFocused = true"
                        @blur="isFocused = false"
                        class="h-11 w-full rounded-md border pl-3.5 pr-11 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('password') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    <button
                        type="button"
                        @mousedown.prevent
                        @click="showPassword = !showPassword"
                        class="absolute right-0 top-0 bottom-0 flex items-center justify-center w-11 h-11 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors focus:outline-hidden cursor-pointer"
                        :aria-label="showPassword ? '{{ __('Ocultar contraseña') }}' : '{{ __('Mostrar contraseña') }}'"
                        tabindex="-1"
                    >
                        {{-- Eye Icon (When Hidden) --}}
                        <svg x-show="!showPassword" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638-0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>

                        {{-- Eye Slash Icon (When Visible) --}}
                        <svg x-show="showPassword" x-cloak class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>

                {{-- Requirements Indicator Panel (Appears on focus) --}}
                <div
                    x-show="isFocused"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    x-cloak
                    class="mt-2 p-3 rounded-md border border-border-subtle bg-surface-canvas flex flex-col gap-1.5 text-xs"
                >
                    <span class="font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Requisitos de la contraseña:') }}
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 font-mono text-xs">
                        <span class="flex items-center gap-1.5 transition-colors" :class="hasLength ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400'">
                            <span class="size-1.5 rounded-full transition-colors" :class="hasLength ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600'"></span>
                            <span>{{ __('8 a 64 caracteres') }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 transition-colors" :class="hasMixed ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400'">
                            <span class="size-1.5 rounded-full transition-colors" :class="hasMixed ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600'"></span>
                            <span>{{ __('Mayúsculas y minúsculas') }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 transition-colors" :class="hasNumber ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400'">
                            <span class="size-1.5 rounded-full transition-colors" :class="hasNumber ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600'"></span>
                            <span>{{ __('Al menos un número') }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 transition-colors" :class="hasSymbol ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400'">
                            <span class="size-1.5 rounded-full transition-colors" :class="hasSymbol ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600'"></span>
                            <span>{{ __('Un símbolo (!@#$%)') }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 transition-colors sm:col-span-2" :class="hasNoSpaces ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500 dark:text-slate-400'">
                            <span class="size-1.5 rounded-full transition-colors" :class="hasNoSpaces ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600'"></span>
                            <span>{{ __('Sin espacios en blanco') }}</span>
                        </span>
                    </div>
                </div>

                @error('password')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password Confirmation Field --}}
            <div
                class="flex flex-col"
                x-data="{ showConfirmation: false }"
            >
                <label for="password_confirmation" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2">
                    {{ __('Confirmar contraseña') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                    <input
                        id="password_confirmation"
                        wire:model="password_confirmation"
                        :type="showConfirmation ? 'text' : 'password'"
                        required
                        autocomplete="new-password"
                        placeholder="Repite la contraseña"
                        class="h-11 w-full rounded-md border pl-3.5 pr-11 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('password_confirmation') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    <button
                        type="button"
                        @mousedown.prevent
                        @click="showConfirmation = !showConfirmation"
                        class="absolute right-0 top-0 bottom-0 flex items-center justify-center w-11 h-11 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors focus:outline-hidden cursor-pointer"
                        :aria-label="showConfirmation ? '{{ __('Ocultar confirmación') }}' : '{{ __('Mostrar confirmación') }}'"
                        tabindex="-1"
                    >
                        <svg x-show="!showConfirmation" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638-0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg x-show="showConfirmation" x-cloak class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Terms and Conditions --}}
            <div class="pt-1">
                <label class="flex items-start gap-3 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        wire:model="terms"
                        class="size-4.5 mt-0.5 rounded-md border-border-strong text-blue-600 focus:ring-2 focus:ring-blue-600 dark:bg-surface-canvas transition-colors"
                    />
                    <span class="text-xs sm:text-sm text-text-secondary leading-snug">
                        {{ __('Acepto los') }}
                        <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">{{ __('Términos y Condiciones') }}</a>
                        {{ __('y la') }}
                        <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">{{ __('Política de Privacidad') }}</a>
                        {{ __('de Turnex.') }}
                    </span>
                </label>
                @error('terms')
                    <p class="mt-1.5 text-xs text-red-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Action CTA & Mobile-First Sticky Action Bar --}}
            <div class="fixed bottom-0 left-0 right-0 p-4 bg-surface-card/95 backdrop-blur-md border-t border-border-subtle sm:static sm:p-0 sm:bg-transparent sm:border-0 sm:backdrop-blur-none z-30 pt-3">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submitStepOne"
                    class="w-full h-12 px-6 rounded-md bg-blue-600 hover:bg-blue-500 text-white font-semibold text-base transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center cursor-pointer shadow-none focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600"
                >
                    <span wire:loading.remove wire:target="submitStepOne" class="inline-flex items-center justify-center gap-2">
                        <span>{{ __('Continuar con verificación') }}</span>
                        <span class="font-mono text-sm leading-none">→</span>
                    </span>
                    <span wire:loading.flex wire:target="submitStepOne" class="items-center justify-center gap-2.5">
                        <svg class="animate-spin size-5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="leading-none">{{ __('Enviando código...') }}</span>
                    </span>
                </button>
            </div>
        </form>

        {{-- Subdued Navigation Footnotes --}}
        <div class="flex flex-col gap-2 pt-3 text-center text-xs sm:text-sm text-text-secondary border-t border-border-subtle mt-3">
            <p>
                {{ __('¿Ya tienes una cuenta?') }}
                <a href="{{ route('login') }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>
                    {{ __('Iniciar sesión') }}
                </a>
            </p>
            <p class="text-xs">
                {{ __('¿Eres cliente y quieres unirte a una fila?') }}
                <a href="{{ route('register') }}" class="font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors" wire:navigate>
                    {{ __('Crear cuenta de cliente') }}
                </a>
            </p>
        </div>
    @elseif ($step === 2)
        {{-- STEP 2: EMAIL VERIFICATION CHALLENGE --}}
        <header class="flex flex-col gap-1.5 mb-1">
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-text-primary">
                {{ __('Verifica tu correo electrónico') }}
            </h1>
            <p class="text-xs sm:text-sm text-text-secondary leading-relaxed">
                {{ __('Ingresa el código de 6 dígitos enviado a') }}
                <span class="font-mono font-semibold text-text-primary">{{ $maskedEmail }}</span>.
                @if (! $isEditingEmail && $emailEditCount < 3)
                    <button
                        type="button"
                        wire:click="editEmail"
                        class="text-blue-600 dark:text-blue-400 hover:underline font-medium inline-flex items-center cursor-pointer ml-1"
                    >
                        <span>{{ __('Cambiar correo') }}</span>
                    </button>
                @endif
            </p>
        </header>

        <div class="flex flex-col gap-6">
            @if ($isEditingEmail)
                <form wire:submit="saveNewEmail" class="flex flex-col gap-3 p-4 rounded-md border border-border-subtle bg-surface-canvas">
                    <div class="flex items-center justify-between">
                        <label for="new_email" class="block text-xs sm:text-sm font-medium text-text-secondary">
                            {{ __('Nuevo correo electrónico') }}
                        </label>
                        @if ($emailEditCount > 0)
                            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">
                                {{ __(':count/3 intentos', ['count' => $emailEditCount]) }}
                            </span>
                        @endif
                    </div>
                    <input
                        id="new_email"
                        wire:model="new_email"
                        type="email"
                        required
                        autofocus
                        placeholder="nuevo-correo@minegocio.com"
                        class="h-11 w-full rounded-md border px-3.5 text-sm sm:text-base transition-colors bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-500 {{ $errors->has('new_email') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    @error('new_email')
                        <p class="text-xs text-red-500 font-medium">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="submit"
                            class="h-9 px-3.5 rounded-md bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold transition-colors cursor-pointer"
                        >
                            {{ __('Actualizar y reenviar') }}
                        </button>
                        <button
                            type="button"
                            wire:click="cancelEditEmail"
                            class="h-9 px-3.5 rounded-md text-xs sm:text-sm font-medium border border-border-strong hover:bg-surface-subtle text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </form>
            @endif

            {{-- 6-Digit OTP Code Form --}}
            <form wire:submit="verifyCode" class="flex flex-col gap-5">
                <div class="flex flex-col items-center">
                    <label for="code" class="block text-xs sm:text-sm font-medium text-text-secondary mb-2 uppercase tracking-wider font-mono">
                        {{ __('Código de 6 dígitos') }}
                    </label>
                    <input
                        id="code"
                        wire:model="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        oninput="this.value = this.value.replace(/\D/g, '').slice(0, 6)"
                        required
                        autofocus
                        placeholder="000000"
                        class="h-16 w-full rounded-md border text-center font-mono text-3xl sm:text-4xl font-bold tracking-widest transition-all bg-surface-card dark:bg-surface-canvas text-text-primary placeholder:text-slate-300 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent dark:placeholder:text-slate-700 {{ $errors->has('code') ? 'border-red-500' : 'border-border-strong' }}"
                    />
                    @error('code')
                        <p class="mt-2 text-xs text-red-500 font-medium text-center">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Action CTA & Mobile-First Sticky Action Bar --}}
                <div class="fixed bottom-0 left-0 right-0 p-4 bg-surface-card/95 backdrop-blur-md border-t border-border-subtle sm:static sm:p-0 sm:bg-transparent sm:border-0 sm:backdrop-blur-none z-30">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="verifyCode"
                        class="w-full h-12 px-6 rounded-md bg-blue-600 hover:bg-blue-500 text-white font-semibold text-base transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center cursor-pointer shadow-none focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-600"
                    >
                        <span wire:loading.remove wire:target="verifyCode" class="inline-flex items-center justify-center gap-2">
                            <span>{{ __('Confirmar y crear negocio') }}</span>
                        </span>
                        <span wire:loading.flex wire:target="verifyCode" class="items-center justify-center gap-2.5">
                            <svg class="animate-spin size-5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="leading-none">{{ __('Verificando...') }}</span>
                        </span>
                    </button>
                </div>
            </form>

            {{-- Resend Controls --}}
            <div class="flex flex-col items-center gap-3 pt-2 text-center text-sm border-t border-border-subtle">
                @if ($cooldownSeconds > 0)
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-xs font-mono font-medium border border-border-subtle bg-surface-subtle text-slate-600 dark:text-slate-400">
                        <svg class="size-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ __('Puedes solicitar un nuevo código en :sec s', ['sec' => $cooldownSeconds]) }}</span>
                    </div>
                @else
                    <button
                        type="button"
                        wire:click="resendOtp"
                        class="h-9 px-3.5 rounded-md text-xs sm:text-sm font-medium border border-border-strong hover:bg-surface-subtle text-slate-700 dark:text-slate-300 transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>{{ __('Reenviar código al correo') }}</span>
                    </button>
                @endif

                <button
                    type="button"
                    wire:click="goToStepOne"
                    class="pt-1.5 text-xs sm:text-sm font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors inline-flex items-center gap-1 cursor-pointer"
                >
                    {{ __('← Corregir datos del negocio (Paso 1)') }}
                </button>
            </div>
        </div>
    @elseif ($step === 3)
        {{-- STEP 3: REGISTRATION SUCCESS / AUTO-REDIRECT TO DASHBOARD --}}
        <div
            x-data
            x-init="setTimeout(() => $wire.finishRegistration(), 1500)"
            class="flex flex-col items-center text-center gap-4 py-8"
        >
            {{-- Success Icon Badge --}}
            <div class="flex size-16 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shadow-xs">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            {{-- Title & Redirect Indicator --}}
            <header class="flex flex-col gap-2 max-w-sm">
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-text-primary">
                    {{ __('¡Negocio registrado con éxito!') }}
                </h1>
                <p class="text-xs sm:text-sm text-text-secondary flex items-center justify-center gap-2">
                    <svg class="animate-spin size-3.5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Redirigiendo al panel de tu negocio...') }}</span>
                </p>
            </header>
        </div>
    @endif
</div>
