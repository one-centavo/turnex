<x-layouts::business.sidebar :title="$title ?? null">
    <flux:main class="bg-surface-canvas min-h-screen pb-24 lg:pb-12">
        {{ $slot }}
    </flux:main>
</x-layouts::business.sidebar>

