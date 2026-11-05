@props([
    'href' => route('home'),
    'size' => 'md',
    'tagline' => __('Turnos Virtuales'),
    'align' => 'left',
])

@php
    $sizeClasses = match ($size) {
        'sm' => [
            'name' => 'text-lg',
            'tagline' => 'text-xs tracking-wider',
        ],
        'lg' => [
            'name' => 'text-3xl',
            'tagline' => 'text-xs tracking-widest',
        ],
        default => [
            'name' => 'text-2xl',
            'tagline' => 'text-xs tracking-widest',
        ],
    };

    $alignClasses = match ($align) {
        'center' => 'items-center text-center',
        'right' => 'items-end text-right',
        default => 'items-start text-left',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "group inline-flex flex-col {$alignClasses} focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-primary rounded-md p-1 transition-opacity hover:opacity-90"]) }} wire:navigate>
        <span class="font-black tracking-tight {{ $sizeClasses['name'] }} text-text-primary leading-none">
            TURN<span class="text-brand-primary">EX</span>
        </span>
        @if ($tagline)
            <span class="font-mono {{ $sizeClasses['tagline'] }} text-text-secondary uppercase mt-1">
                {{ $tagline }}
            </span>
        @endif
    </a>
@else
    <div {{ $attributes->merge(['class' => "inline-flex flex-col {$alignClasses}"]) }}>
        <span class="font-black tracking-tight {{ $sizeClasses['name'] }} text-text-primary leading-none">
            TURN<span class="text-brand-primary">EX</span>
        </span>
        @if ($tagline)
            <span class="font-mono {{ $sizeClasses['tagline'] }} text-text-secondary uppercase mt-1">
                {{ $tagline }}
            </span>
        @endif
    </div>
@endif

