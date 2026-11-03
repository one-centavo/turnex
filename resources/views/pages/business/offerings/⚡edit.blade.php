<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Offering;
use App\Services\OfferingService;
use App\Support\DataNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editar Producto o Servicio')] #[Layout('layouts.business')] class extends Component
{
    use WithFileUploads;

    public Offering $offering;

    public string $name = '';

    public int|string|null $category_id = null;

    public string $description = '';

    public float|string|null $price = null;

    public int|string|null $estimated_duration_minutes = null;

    public bool $is_active = true;

    public ?string $existing_image = null;

    public mixed $image = null;

    public bool $remove_image = false;

    /**
     * Initialize component state and verify tenant ownership.
     */
    public function mount(Offering $offering): void
    {
        if ($offering->business_id !== Auth::guard('business')->id()) {
            abort(404);
        }

        $this->offering = $offering;
        $this->name = $offering->name;
        $this->category_id = $offering->category_id;
        $this->description = $offering->description ?? '';
        $this->price = (float) $offering->price;
        $this->estimated_duration_minutes = $offering->estimated_duration_minutes;
        $this->is_active = (bool) $offering->is_active;
        $this->existing_image = $offering->reference_image;
        $this->image = null;
        $this->remove_image = false;
    }

    /**
     * Get active categories for selection, preserving assigned archived category if present.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Category> $activeCategories */
        $activeCategories = Category::active()->orderBy('name')->get();

        $assignedCategory = $this->offering->category;

        if ($assignedCategory instanceof Category) {
            $isArchived = $assignedCategory->trashed() || ! $assignedCategory->is_active;

            if ($isArchived && ! $activeCategories->contains('id', $assignedCategory->id)) {
                $archivedCategory = clone $assignedCategory;
                $archivedCategory->name = "{$assignedCategory->name} (Archived)";

                return $activeCategories->concat([$archivedCategory]);
            }
        }

        return $activeCategories;
    }

    /**
     * Validation rules for offering updates.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50', 'regex:/[^\s]/'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query): void {
                    if ((int) $this->category_id === (int) $this->offering->category_id) {
                        return;
                    }

                    $query->where('is_active', true)->whereNull('deleted_at');
                }),
            ],
            'image' => ['nullable', 'image:allow_svg', 'mimes:png,jpg,jpeg,svg', 'max:10240'],
            'description' => ['nullable', 'string', 'max:250'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
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
            'name.regex' => __('El nombre no puede consistir únicamente de espacios en blanco.'),
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
     * Validate uploaded image immediately and preserve existing preview if invalid.
     */
    public function updatedImage(): void
    {
        try {
            $this->validateOnly('image');
            $this->remove_image = false;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->image = null;
            throw $e;
        }
    }

    /**
     * Discard the temporary image upload and revert back to the saved image preview.
     */
    public function discardTempImage(): void
    {
        $this->image = null;
        $this->resetErrorBag('image');
    }

    /**
     * Explicitly remove the saved image, reverting to placeholder on save.
     */
    public function removeExistingImage(): void
    {
        $this->image = null;
        $this->remove_image = true;
        $this->resetErrorBag('image');
    }

    /**
     * Undo explicit removal and restore saved image preview.
     */
    public function restoreExistingImage(): void
    {
        $this->remove_image = false;
        $this->resetErrorBag('image');
    }

    /**
     * General remove image action delegating to appropriate action based on state.
     */
    public function removeImage(): void
    {
        if ($this->image !== null) {
            $this->discardTempImage();
        } else {
            $this->removeExistingImage();
        }
    }

    /**
     * Update offering persistence via OfferingService.
     */
    public function save(OfferingService $offeringService): void
    {
        if ($this->offering->business_id !== Auth::guard('business')->id()) {
            abort(404);
        }

        $this->name = DataNormalizer::text($this->name);
        $this->description = DataNormalizer::nullableText($this->description) ?? '';

        $this->validate();

        $price = (isset($this->price) && $this->price !== '') ? (float) $this->price : 0.00;
        $duration = (isset($this->estimated_duration_minutes) && $this->estimated_duration_minutes !== '')
            ? (int) $this->estimated_duration_minutes
            : null;

        $offeringService->update(
            offering: $this->offering,
            data: [
                'name' => $this->name,
                'category_id' => (int) $this->category_id,
                'description' => filled($this->description) ? $this->description : null,
                'price' => $price,
                'estimated_duration_minutes' => $duration,
                'is_active' => (bool) $this->is_active,
            ],
            image: $this->image instanceof UploadedFile ? $this->image : null,
            removeImage: $this->remove_image,
        );

        session()->flash('status', __('Service updated successfully.'));

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
            {{ __('Editar Producto o Servicio') }}
        </h1>
        <p class="mt-1 text-sm text-text-secondary">
            {{ __('Actualiza la información comercial, precios, duración y estado de este producto o servicio en tu catálogo.') }}
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

            <flux:field>
                <flux:switch
                    wire:model="is_active"
                    :label="__('Servicio activo')"
                    :description="__('Define si el producto o servicio está visible y disponible para los clientes en tu catálogo comercial.')"
                    data-test="offering-status-toggle"
                />
            </flux:field>

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
                                wire:click="discardTempImage"
                                class="absolute -top-2 -right-2 inline-flex items-center justify-center size-6 rounded-full bg-red-600 text-white shadow-xs hover:bg-red-700 focus:outline-hidden cursor-pointer transition-colors"
                                title="{{ __('Descartar imagen') }}"
                                aria-label="{{ __('Descartar imagen') }}"
                                data-test="discard-image-badge-button"
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
                                    {{ __('Nueva imagen lista para guardar') }}
                                </p>
                            </div>
                            <div>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    wire:click="discardTempImage"
                                    icon="trash"
                                    class="cursor-pointer text-red-600 hover:text-red-700"
                                    data-test="discard-image-button"
                                >
                                    {{ __('Descartar imagen') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @elseif ($existing_image && ! $remove_image)
                    <div class="mt-3 flex items-start gap-4 p-3 rounded-lg border border-border-subtle bg-surface-subtle">
                        <div class="relative group">
                            <img
                                src="{{ Storage::disk('public')->url($existing_image) }}"
                                alt="{{ $name }}"
                                class="size-24 rounded-lg object-cover border border-border-subtle shadow-xs"
                                data-test="existing-image-preview"
                            />
                            <button
                                type="button"
                                wire:click="removeExistingImage"
                                class="absolute -top-2 -right-2 inline-flex items-center justify-center size-6 rounded-full bg-red-600 text-white shadow-xs hover:bg-red-700 focus:outline-hidden cursor-pointer transition-colors"
                                title="{{ __('Eliminar imagen actual') }}"
                                aria-label="{{ __('Eliminar imagen actual') }}"
                                data-test="remove-image-badge-button"
                            >
                                <flux:icon.x-mark class="size-3.5" />
                            </button>
                        </div>
                        <div class="flex-1 flex flex-col justify-between self-stretch">
                            <div>
                                <p class="text-xs font-medium text-text-primary truncate">
                                    {{ __('Imagen guardada') }}
                                </p>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    {{ __('Esta imagen está actualmente asociada al servicio.') }}
                                </p>
                            </div>
                            <div>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    wire:click="removeExistingImage"
                                    icon="trash"
                                    class="cursor-pointer text-red-600 hover:text-red-700"
                                    data-test="remove-image-button"
                                >
                                    {{ __('Eliminar imagen') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-3 flex items-center gap-4 p-3 rounded-lg border border-dashed border-border-subtle bg-surface-subtle/50" data-test="image-placeholder">
                        <div class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-surface-subtle text-text-secondary">
                            <flux:icon.shopping-bag class="size-6 text-text-secondary" />
                        </div>
                        <div class="flex-1">
                            @if ($remove_image && $existing_image)
                                <p class="text-xs font-medium text-amber-600 dark:text-amber-400">
                                    {{ __('Imagen marcada para eliminación') }}
                                </p>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    {{ __('Se eliminará permanentemente al guardar los cambios.') }}
                                </p>
                                <button
                                    type="button"
                                    wire:click="restoreExistingImage"
                                    class="mt-1 text-xs text-brand-primary hover:underline cursor-pointer font-medium"
                                    data-test="restore-image-button"
                                >
                                    {{ __('Deshacer y conservar imagen actual') }}
                                </button>
                            @else
                                <p class="text-xs font-medium text-text-primary">
                                    {{ __('Sin imagen de referencia') }}
                                </p>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    {{ __('Puedes subir una imagen para mostrarla en el catálogo comercial.') }}
                                </p>
                            @endif
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
                    data-test="cancel-offering-button"
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
                        {{ __('Guardar Cambios') }}
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
