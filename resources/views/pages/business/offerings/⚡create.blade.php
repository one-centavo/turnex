<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Category;
use App\Services\OfferingService;
use App\Support\DataNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Nuevo Producto o Servicio')] #[Layout('layouts.business')] class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public int|string|null $category_id = null;

    public string $description = '';

    public float|string|null $price = null;

    public int|string|null $estimated_duration_minutes = null;

    public mixed $image = null;

    /**
     * Get active categories ordered by name for dropdown selection.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::active()->orderBy('name')->get();
    }

    /**
     * Validation rules for offering creation.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'image' => ['nullable', 'image:allow_svg', 'mimes:png,jpg,svg', 'max:10240'],
            'description' => ['nullable', 'string', 'max:250'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => __('El nombre del servicio es obligatorio.'),
            'name.min' => __('El nombre debe tener al menos 3 caracteres.'),
            'name.max' => __('El nombre no puede superar los 50 caracteres.'),
            'category_id.required' => __('Debes seleccionar una categoría.'),
            'category_id.exists' => __('La categoría seleccionada no es válida o está inactiva.'),
            'image.image' => __('El archivo debe ser una imagen válida.'),
            'image.mimes' => __('La imagen debe ser de formato PNG, JPG o SVG.'),
            'image.max' => __('La imagen no debe superar los 10 MB.'),
            'description.max' => __('La descripción no puede superar los 250 caracteres.'),
            'price.numeric' => __('El precio debe ser un número válido.'),
            'price.min' => __('El precio no puede ser negativo.'),
            'price.max' => __('El precio no puede superar 99,999,999.99.'),
            'estimated_duration_minutes.integer' => __('La duración debe ser un número entero de minutos.'),
            'estimated_duration_minutes.min' => __('La duración estimada debe ser de al menos 1 minuto.'),
        ];
    }

    /**
     * Remove the currently selected image preview.
     */
    public function removeImage(): void
    {
        $this->image = null;
    }

    /**
     * Save the offering to persistence via OfferingService.
     */
    public function save(OfferingService $offeringService): void
    {
        /** @var Business|null $business */
        $business = Auth::guard('business')->user();

        if (! $business instanceof Business) {
            throw new RuntimeException('Authenticated business is required to create offerings.');
        }

        $this->name = DataNormalizer::text($this->name);
        $this->description = DataNormalizer::nullableText($this->description) ?? '';

        $this->validate();

        $price = (isset($this->price) && $this->price !== '') ? (float) $this->price : 0.00;
        $duration = (isset($this->estimated_duration_minutes) && $this->estimated_duration_minutes !== '')
            ? (int) $this->estimated_duration_minutes
            : null;

        $offeringService->create(
            business: $business,
            data: [
                'name' => $this->name,
                'category_id' => (int) $this->category_id,
                'description' => filled($this->description) ? $this->description : null,
                'price' => $price,
                'estimated_duration_minutes' => $duration,
            ],
            image: $this->image instanceof UploadedFile ? $this->image : null,
        );

        session()->flash('status', __('Service created successfully.'));

        $this->reset(['name', 'category_id', 'description', 'price', 'estimated_duration_minutes', 'image']);

        $this->redirect(route('business.offerings.index'), navigate: true);
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center gap-3">
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route('business.offerings.index')"
            wire:navigate
            class="cursor-pointer"
        >
            {{ __('Volver al catálogo') }}
        </flux:button>
    </div>

    <div>
        <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">
            {{ __('Nuevo Producto o Servicio') }}
        </h1>
        <p class="mt-1 text-sm text-text-secondary">
            {{ __('Completa la información básica para registrar este servicio o producto en tu catálogo comercial.') }}
        </p>
    </div>

    <div class="rounded-xl border border-border-subtle bg-surface-card p-6 sm:p-8 shadow-xs">
        <form wire:submit="save" class="space-y-6">
            <flux:input
                wire:model="name"
                :label="__('Nombre del servicio o producto')"
                placeholder="{{ __('Ej. Corte de cabello clásico') }}"
                required
                maxlength="50"
                autocomplete="off"
                data-test="offering-name-input"
            />

            <flux:select
                wire:model="category_id"
                :label="__('Categoría')"
                placeholder="{{ __('Selecciona una categoría') }}"
                required
                data-test="offering-category-select"
            >
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input
                    wire:model="price"
                    :label="__('Precio (COP)')"
                    type="number"
                    step="0.01"
                    min="0"
                    max="99999999.99"
                    placeholder="0.00"
                    description="{{ __('Opcional. Deja en 0 para servicios gratuitos.') }}"
                    data-test="offering-price-input"
                />

                <flux:input
                    wire:model="estimated_duration_minutes"
                    :label="__('Duración estimada (minutos)')"
                    type="number"
                    min="1"
                    step="1"
                    placeholder="15"
                    description="{{ __('Opcional. Mínimo 1 minuto.') }}"
                    data-test="offering-duration-input"
                />
            </div>

            <flux:textarea
                wire:model="description"
                :label="__('Descripción')"
                rows="3"
                maxlength="250"
                placeholder="{{ __('Describe brevemente los detalles o beneficios de este producto o servicio (máximo 250 caracteres)...') }}"
                data-test="offering-description-input"
            />

            <div>
                <flux:field>
                    <flux:label>{{ __('Imagen de referencia') }}</flux:label>
                    <flux:description>{{ __('Opcional. Formatos permitidos: PNG, JPG o SVG (máximo 10 MB).') }}</flux:description>
                    <flux:input
                        type="file"
                        wire:model="image"
                        accept="image/png,image/jpeg,image/svg+xml"
                        data-test="offering-image-input"
                    />
                    <flux:error name="image" />
                </flux:field>

                <div wire:loading wire:target="image" class="flex items-center gap-2 text-xs text-text-secondary mt-1">
                    <flux:icon.arrow-path class="size-3.5 animate-spin text-brand-primary" />
                    <span>{{ __('Cargando imagen...') }}</span>
                </div>

                @if ($image)
                    @php
                        $previewUrl = null;
                        if (is_object($image) && method_exists($image, 'temporaryUrl')) {
                            try {
                                $previewUrl = $image->temporaryUrl();
                            } catch (\Throwable) {
                                $previewUrl = null;
                            }
                        }
                    @endphp
                    <div class="mt-3 flex items-start gap-4 p-3 rounded-lg border border-border-subtle bg-surface-subtle">
                        <div class="relative group">
                            @if ($previewUrl)
                                <img
                                    src="{{ $previewUrl }}"
                                    alt="{{ __('Vista previa') }}"
                                    class="size-24 rounded-lg object-cover border border-border-subtle shadow-xs"
                                    data-test="image-preview"
                                />
                            @endif
                            <button
                                type="button"
                                wire:click="removeImage"
                                class="absolute -top-2 -right-2 inline-flex items-center justify-center size-6 rounded-full bg-red-600 text-white shadow-xs hover:bg-red-700 focus:outline-hidden cursor-pointer transition-colors"
                                title="{{ __('Eliminar imagen') }}"
                                aria-label="{{ __('Eliminar imagen') }}"
                                data-test="remove-image-badge-button"
                            >
                                <flux:icon.x-mark class="size-3.5" />
                            </button>
                        </div>
                        <div class="flex-1 flex flex-col justify-between self-stretch">
                            <div>
                                <p class="text-xs font-medium text-text-primary truncate">
                                    {{ method_exists($image, 'getClientOriginalName') ? $image->getClientOriginalName() : __('Archivo seleccionado') }}
                                </p>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    {{ __('Imagen lista para guardar') }}
                                </p>
                            </div>
                            <div>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    wire:click="removeImage"
                                    icon="trash"
                                    class="cursor-pointer text-red-600 hover:text-red-700"
                                    data-test="remove-image-button"
                                >
                                    {{ __('Quitar imagen') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-border-subtle">
                <flux:button
                    variant="ghost"
                    :href="route('business.offerings.index')"
                    wire:navigate
                    class="cursor-pointer"
                >
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save, image"
                    class="cursor-pointer"
                    data-test="submit-offering-button"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ __('Crear Producto o Servicio') }}
                    </span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Guardando...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
