<x-layouts::business.sidebar :title="$title ?? null">
    <flux:main class="bg-surface-canvas min-h-screen">
        {{ $slot }}
    </flux:main>
</x-layouts::business.sidebar>

