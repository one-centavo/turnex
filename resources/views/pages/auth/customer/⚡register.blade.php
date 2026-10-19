<?php

declare(strict_types=1);

use App\Enums\ContactType;
use App\Exceptions\OtpCooldownException;
use App\Rules\CustomerRules;
use App\Services\CustomerService;
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

new #[Title('Crear cuenta de cliente')] #[Layout('layouts.auth.card')] class extends Component
{
    public int $step = 1;

    // Step 1 Form fields
    public string $first_name = '';

    public string $last_name = '';

    public string $phone_number = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    // Step 2 Verification fields
    public string $code = '';

    public string $channel = 'whatsapp';

    public int $phoneEditCount = 0;

    public bool $isEditingPhone = false;

    public string $new_phone_number = '';

    public int $cooldownSeconds = 0;

    public string $maskedPhone = '';

    public function mount(): void
    {
        if (Auth::guard('customer')->check() || Auth::check()) {
            $this->redirectIntended(default: route('home'), navigate: true);

            return;
        }

        /** @var array{step?: int, first_name?: string, last_name?: string, email?: string, phone_number?: string, phone_edit_count?: int, cooldown_until?: string, expires_at?: string}|null $sessionData */
        $sessionData = Session::get('customer_registration');

        if ($sessionData !== null) {
            if (isset($sessionData['expires_at']) && Carbon::parse($sessionData['expires_at'])->isPast()) {
                Session::forget('customer_registration');
                $this->step = 1;

                return;
            }

            if (($sessionData['step'] ?? 1) === 2 && ! empty($sessionData['phone_number'])) {
                $this->step = 2;
                $this->first_name = $sessionData['first_name'] ?? '';
                $this->last_name = $sessionData['last_name'] ?? '';
                $this->email = $sessionData['email'] ?? '';
                $this->phone_number = $sessionData['phone_number'];
                $this->phoneEditCount = $sessionData['phone_edit_count'] ?? 0;
                $this->maskedPhone = $this->maskPhoneNumber($this->phone_number);
                $this->syncCooldown();
            }
        }
    }

    public function submitStepOne(OtpService $otpService): void
    {
        $ip = request()->ip() ?? '127.0.0.1';
        $rateKey = 'customer_reg_ip:'.$ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->addError('general', "Has realizado demasiados intentos. Por favor espera {$seconds} segundos antes de intentar nuevamente.");

            return;
        }

        $this->first_name = DataNormalizer::name($this->first_name);
        $this->last_name = DataNormalizer::name($this->last_name);
        $this->email = DataNormalizer::email($this->email);

        $this->validate([
            'first_name' => CustomerRules::firstName(),
            'last_name' => CustomerRules::lastName(),
            'email' => CustomerRules::email(),
            'phone_number' => CustomerRules::phone(),
            'password' => CustomerRules::password(),
            'terms' => CustomerRules::terms(),
        ], CustomerRules::messages());

        RateLimiter::hit($rateKey, 60);

        $normalizedPhone = DataNormalizer::phone($this->phone_number);
        $normalizedEmail = $this->email;
        $normalizedFirstName = $this->first_name;
        $normalizedLastName = $this->last_name;

        try {
            $otpService->generate($normalizedPhone, ContactType::WhatsApp);
        } catch (OtpCooldownException $e) {
            $this->cooldownSeconds = $e->remainingSeconds;
        } catch (\Throwable $e) {
            $this->addError('general', 'No fue posible despachar el código por WhatsApp en este momento. Intenta nuevamente.');

            return;
        }

        $cooldownUntil = now()->addSeconds($this->cooldownSeconds > 0 ? $this->cooldownSeconds : OtpService::COOLDOWN_SECONDS);

        Session::put('customer_registration', [
            'step' => 2,
            'first_name' => $normalizedFirstName,
            'last_name' => $normalizedLastName,
            'email' => $normalizedEmail,
            'phone_number' => $normalizedPhone,
            'password' => $this->password,
            'phone_edit_count' => 0,
            'cooldown_until' => $cooldownUntil->toIso8601String(),
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);

        $this->step = 2;
        $this->phone_number = $normalizedPhone;
        $this->maskedPhone = $this->maskPhoneNumber($normalizedPhone);
        $this->cooldownSeconds = max(0, (int) now()->diffInSeconds($cooldownUntil, false));
        $this->channel = 'whatsapp';
        $this->code = '';

        Flux::toast(variant: 'success', text: __('Código de verificación enviado por WhatsApp.'));
    }

    public function verifyCode(CustomerService $customerService, OtpService $otpService): void
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

        /** @var array{first_name: string, last_name: string, email: string, phone_number: string, password: string} $sessionData */
        $sessionData = Session::get('customer_registration');
        $channelEnum = $this->channel === 'sms' ? ContactType::Sms : ContactType::WhatsApp;

        $isValid = $otpService->verify($sessionData['phone_number'], $this->code, $channelEnum);

        if (! $isValid) {
            $this->addError('code', 'El código ingresado es incorrecto o ha expirado.');

            return;
        }

        $customer = $customerService->register([
            'first_name' => $sessionData['first_name'],
            'last_name' => $sessionData['last_name'],
            'email' => $sessionData['email'],
            'phone_number' => $sessionData['phone_number'],
            'password' => $sessionData['password'],
        ]);

        Session::forget('customer_registration');

        Auth::guard('customer')->login($customer);
        Session::regenerate();

        Flux::toast(variant: 'success', text: __('¡Bienvenido a Turnex, :name! Tu cuenta ha sido creada.', ['name' => $customer->first_name]));

        $this->redirectIntended(default: route('home'), navigate: true);
    }

    public function resendOtp(string $channel, OtpService $otpService): void
    {
        $this->resetErrorBag('code');

        if ($this->hasSessionExpired()) {
            $this->handleExpiredSession();

            return;
        }

        /** @var array{step: int, first_name: string, last_name: string, email: string, phone_number: string, password: string, phone_edit_count: int, cooldown_until: string, expires_at: string} $sessionData */
        $sessionData = Session::get('customer_registration');

        $this->syncCooldown();

        if ($this->cooldownSeconds > 0) {
            $this->addError('code', "Debes esperar {$this->cooldownSeconds} segundos antes de solicitar otro código.");

            return;
        }

        $targetChannel = $channel === 'sms' ? ContactType::Sms : ContactType::WhatsApp;
        $this->channel = $channel;

        try {
            $otpService->generate($sessionData['phone_number'], $targetChannel);
            $newCooldownUntil = now()->addSeconds(OtpService::COOLDOWN_SECONDS);
            $sessionData['cooldown_until'] = $newCooldownUntil->toIso8601String();
            Session::put('customer_registration', $sessionData);
            $this->cooldownSeconds = OtpService::COOLDOWN_SECONDS;

            $channelLabel = $channel === 'sms' ? 'SMS' : 'WhatsApp';
            Flux::toast(variant: 'success', text: __("Nuevo código enviado por {$channelLabel}."));
        } catch (OtpCooldownException $e) {
            $this->cooldownSeconds = $e->remainingSeconds;
            $this->addError('code', "Espera {$this->cooldownSeconds} segundos antes de volver a solicitar un código.");
        } catch (\Throwable $e) {
            $this->addError('code', 'Ocurrió un error al despachar el código. Intenta de nuevo.');
        }
    }

    public function editPhone(): void
    {
        if ($this->phoneEditCount >= 3) {
            $this->addError('phone_edit', 'Has alcanzado el límite máximo de 3 ediciones de número de celular.');

            return;
        }

        $this->new_phone_number = $this->phone_number;
        $this->isEditingPhone = true;
    }

    public function cancelEditPhone(): void
    {
        $this->isEditingPhone = false;
        $this->new_phone_number = '';
        $this->resetErrorBag(['new_phone_number', 'phone_edit']);
    }

    public function saveNewPhone(OtpService $otpService): void
    {
        if ($this->hasSessionExpired()) {
            $this->handleExpiredSession();

            return;
        }

        /** @var array{step: int, first_name: string, last_name: string, email: string, phone_number: string, password: string, phone_edit_count: int, cooldown_until: string, expires_at: string} $sessionData */
        $sessionData = Session::get('customer_registration');

        if ($sessionData['phone_edit_count'] >= 3) {
            $this->addError('new_phone_number', 'Has alcanzado el límite de 3 cambios de número. Debes reiniciar el registro.');

            return;
        }

        $this->validate([
            'new_phone_number' => CustomerRules::phone(),
        ], CustomerRules::messages());

        $normalized = DataNormalizer::phone($this->new_phone_number);

        $newEditCount = $sessionData['phone_edit_count'] + 1;
        $sessionData['phone_edit_count'] = $newEditCount;
        $sessionData['phone_number'] = $normalized;

        $targetChannel = $this->channel === 'sms' ? ContactType::Sms : ContactType::WhatsApp;

        try {
            $otpService->generate($normalized, $targetChannel);
            $sessionData['cooldown_until'] = now()->addSeconds(OtpService::COOLDOWN_SECONDS)->toIso8601String();
            $this->cooldownSeconds = OtpService::COOLDOWN_SECONDS;
        } catch (OtpCooldownException $e) {
            // Unified cooldown preserved!
            $this->cooldownSeconds = $e->remainingSeconds;
        } catch (\Throwable $e) {
            // Keep going, user can request resend
        }

        Session::put('customer_registration', $sessionData);

        $this->phone_number = $normalized;
        $this->phoneEditCount = $newEditCount;
        $this->maskedPhone = $this->maskPhoneNumber($normalized);
        $this->isEditingPhone = false;
        $this->code = '';

        Flux::toast(variant: 'success', text: __('Número actualizado exitosamente.'));
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
        $sessionData = Session::get('customer_registration');
        if (isset($sessionData['cooldown_until'])) {
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
        $sessionData = Session::get('customer_registration');

        if (! $sessionData || ! isset($sessionData['expires_at'])) {
            return true;
        }

        return Carbon::parse($sessionData['expires_at'])->isPast();
    }

    private function handleExpiredSession(): void
    {
        Session::forget('customer_registration');
        $this->step = 1;
        $this->reset(['code', 'phoneEditCount', 'isEditingPhone']);
        $this->addError('general', 'Tu sesión ha expirado tras 15 minutos de inactividad. Por favor, completa tus datos nuevamente.');
        Flux::toast(variant: 'warning', text: __('Sesión expirada. Por favor reinicia tu registro.'));
    }

    private function maskPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/[^\d]/', '', $phone) ?? '';
        if (strlen($digits) >= 10) {
            $lastTwo = substr($digits, -2);
            $prefix = substr($digits, -10, 3);

            return "+57 {$prefix} ••• ••{$lastTwo}";
        }

        return $phone;
    }
};
?>

<div class="flex flex-col gap-6" @if($step === 2 && $cooldownSeconds > 0) wire:poll.1s="decrementCooldown" @endif>
    @error('general')
        <div class="rounded-md border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-600 dark:text-red-400">
            {{ $message }}
        </div>
    @enderror

    @if ($step === 1)
        {{-- STEP 1: ACCOUNT INFORMATION --}}
        <x-auth-header
            :title="__('Crea tu cuenta de cliente')"
            :description="__('Regístrate para unirte a filas virtuales y realizar pedidos en tiempo real.')"
        />

        <form wire:submit="submitStepOne" class="flex flex-col gap-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="first_name"
                    :label="__('Nombre')"
                    type="text"
                    required
                    autofocus
                    autocomplete="given-name"
                    placeholder="Ej. Carlos"
                />

                <flux:input
                    wire:model="last_name"
                    :label="__('Apellido')"
                    type="text"
                    required
                    autocomplete="family-name"
                    placeholder="Ej. Pérez"
                />
            </div>

            <flux:input
                wire:model="phone_number"
                :label="__('Número de celular')"
                type="tel"
                required
                autocomplete="tel"
                placeholder="Ej. 300 123 4567"
            >
                <x-slot:description>
                    {{ __('Te enviaremos un código de seguridad por WhatsApp.') }}
                </x-slot:description>
            </flux:input>

            <flux:input
                wire:model="email"
                :label="__('Correo electrónico')"
                type="email"
                required
                autocomplete="email"
                placeholder="nombre@ejemplo.com"
            />

            <flux:input
                wire:model="password"
                :label="__('Contraseña')"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Mínimo 8 caracteres"
                viewable
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirmar contraseña')"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Repite tu contraseña"
                viewable
            />

            <div class="pt-2">
                <flux:checkbox
                    wire:model="terms"
                    :label="__('Acepto los Términos y Condiciones y la Política de Privacidad de Turnex.')"
                />
                @error('terms')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div class="flex flex-col gap-3 pt-2">
                <flux:button type="submit" variant="primary" class="w-full">
                    <span wire:loading.remove wire:target="submitStepOne">{{ __('Continuar con verificación') }}</span>
                    <span wire:loading wire:target="submitStepOne">{{ __('Enviando código...') }}</span>
                </flux:button>
            </div>
        </form>

        <div class="flex flex-col gap-2 pt-2 text-center text-sm text-neutral-600 dark:text-neutral-400">
            <p>
                {{ __('¿Ya tienes una cuenta?') }}
                <flux:link href="{{ route('login') }}" class="font-medium text-blue-600 dark:text-blue-400" wire:navigate>
                    {{ __('Iniciar sesión') }}
                </flux:link>
            </p>
            <p class="text-xs">
                {{ __('¿Administras un establecimiento comercial?') }}
                <flux:link href="{{ route('home') }}" class="font-medium text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300">
                    {{ __('Registra tu negocio') }}
                </flux:link>
            </p>
        </div>
    @else
        {{-- STEP 2: PHONE VERIFICATION CHALLENGE --}}
        <x-auth-header
            :title="__('Verifica tu número')"
            :description="__('Ingresamos el código de 6 dígitos enviado por :channel a tu celular.', ['channel' => $channel === 'sms' ? 'SMS' : 'WhatsApp'])"
        />

        <div class="flex flex-col gap-6">
            {{-- Phone number summary & Edit toggle --}}
            <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Código enviado a:') }}</span>
                        <p class="font-mono text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $maskedPhone }}</p>
                    </div>

                    @if (! $isEditingPhone && $phoneEditCount < 3)
                        <flux:button size="sm" variant="subtle" wire:click="editPhone">
                            {{ __('Cambiar número') }}
                        </flux:button>
                    @endif
                </div>

                @if ($phoneEditCount > 0)
                    <p class="text-xs text-neutral-400">
                        {{ __('Has cambiado de número :count de 3 veces permitidas.', ['count' => $phoneEditCount]) }}
                    </p>
                @endif

                @if ($isEditingPhone)
                    <form wire:submit="saveNewPhone" class="flex flex-col gap-3 pt-2">
                        <flux:input
                            wire:model="new_phone_number"
                            :label="__('Nuevo celular')"
                            type="tel"
                            placeholder="Ej. 310 987 6543"
                            required
                        />

                        <div class="flex items-center gap-2">
                            <flux:button size="sm" type="submit" variant="primary">
                                {{ __('Actualizar y reenviar') }}
                            </flux:button>
                            <flux:button size="sm" type="button" variant="subtle" wire:click="cancelEditPhone">
                                {{ __('Cancelar') }}
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- 6-Digit Code Form --}}
            <form wire:submit="verifyCode" class="flex flex-col gap-4">
                <flux:field>
                    <flux:label class="text-center">{{ __('Código de 6 dígitos') }}</flux:label>
                    <flux:input
                        wire:model="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        autofocus
                        class="text-center font-mono text-2xl tracking-widest"
                        placeholder="000000"
                    />
                    <flux:error name="code" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">
                    <span wire:loading.remove wire:target="verifyCode">{{ __('Confirmar y crear cuenta') }}</span>
                    <span wire:loading wire:target="verifyCode">{{ __('Verificando...') }}</span>
                </flux:button>
            </form>

            {{-- Resend & Channel Switch Options --}}
            <div class="flex flex-col items-center gap-3 pt-2 text-center text-sm">
                @if ($cooldownSeconds > 0)
                    <p class="font-mono text-xs text-neutral-500">
                        {{ __('Puedes solicitar un nuevo código en :sec s', ['sec' => $cooldownSeconds]) }}
                    </p>
                @else
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <flux:button size="sm" variant="subtle" wire:click="resendOtp('whatsapp')">
                            {{ __('Reenviar por WhatsApp') }}
                        </flux:button>
                        <flux:button size="sm" variant="subtle" wire:click="resendOtp('sms')">
                            {{ __('Reenviar por SMS') }}
                        </flux:button>
                    </div>
                @endif

                <button
                    type="button"
                    wire:click="goToStepOne"
                    class="pt-2 text-xs text-neutral-500 underline hover:text-neutral-700 dark:hover:text-neutral-300"
                >
                    {{ __('← Corregir datos personales (Paso 1)') }}
                </button>
            </div>
        </div>
    @endif
</div>
