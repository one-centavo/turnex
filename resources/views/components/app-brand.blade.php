@props([
    'href' => route('home'),
    'size' => 'md',
    'tagline' => __('Turnos Virtuales'),
    'align' => 'left',
    'icon' => false,
])

@php
    $sizeClasses = match ($size) {
        'sm' => [
            'name' => 'text-lg',
            'tagline' => 'text-xs tracking-wider',
            'icon' => 'size-6',
        ],
        'lg' => [
            'name' => 'text-2xl md:text-3xl',
            'tagline' => 'text-xs tracking-widest',
            'icon' => 'size-10',
        ],
        default => [
            'name' => 'text-lg md:text-2xl',
            'tagline' => 'text-xs tracking-wider md:tracking-widest',
            'icon' => 'size-8',
        ],
    };

    $alignClasses = match ($align) {
        'center' => 'items-center text-center',
        'right' => 'items-end text-right',
        default => 'items-start text-left',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "group inline-flex items-center gap-2.5 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-primary rounded-md p-1 transition-opacity hover:opacity-90"]) }} wire:navigate>
        @if ($icon)
            <x-app-logo-icon class="{{ $sizeClasses['icon'] }} text-brand-primary fill-current shrink-0" />
        @endif
        <div class="flex flex-col {{ $alignClasses }}">
            <span class="font-black tracking-tight {{ $sizeClasses['name'] }} text-text-primary leading-none">
                TURN<span class="text-brand-primary">EX</span>
            </span>
            @if ($tagline)
                <span class="font-mono {{ $sizeClasses['tagline'] }} text-text-secondary uppercase mt-1">
                    {{ $tagline }}
                </span>
            @endif
        </div>
    </a>
@else
    <div {{ $attributes->merge(['class' => "inline-flex items-center gap-2.5"]) }}>
        @if ($icon)
            <x-app-logo-icon class="{{ $sizeClasses['icon'] }} text-brand-primary fill-current shrink-0" />
        @endif
        <div class="flex flex-col {{ $alignClasses }}">
            <span class="font-black tracking-tight {{ $sizeClasses['name'] }} text-text-primary leading-none">
                TURN<span class="text-brand-primary">EX</span>
            </span>
            @if ($tagline)
                <span class="font-mono {{ $sizeClasses['tagline'] }} text-text-secondary uppercase mt-1">
                    {{ $tagline }}
                </span>
            @endif
        </div>
    </div>
@endif

