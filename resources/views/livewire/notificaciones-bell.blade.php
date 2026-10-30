<div wire:poll.30s x-data="{ open: false }" class="relative">
    <button
        type="button"
        @click="open = !open"
        class="relative flex h-10 w-10 items-center justify-center rounded-full border border-cosechar-border bg-white text-cosechar-muted hover:text-cosechar-ink"
        aria-label="Notificaciones"
    >
        <x-icon name="bell" class="h-5 w-5" />
        @if ($this->total > 0)
            <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-cosechar-danger px-1 text-[11px] font-semibold text-white">
                {{ $this->total }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-cosechar-border bg-white p-2 shadow-lg"
    >
        @if ($this->total === 0)
            <p class="px-3 py-4 text-center text-sm text-cosechar-muted">Sin alertas pendientes.</p>
        @else
            @if ($this->stockBajo > 0)
                <a href="{{ route('productos.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-cosechar-cream">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F3E0DC] text-cosechar-danger">
                        <x-icon name="warning" class="h-4 w-4" />
                    </span>
                    <span>
                        <span class="block font-medium text-cosechar-ink">{{ $this->stockBajo }} {{ Str::plural('producto', $this->stockBajo) }} con stock bajo</span>
                        <span class="block text-xs text-cosechar-muted">Ver en Productos</span>
                    </span>
                </a>
            @endif

            @if ($this->porVencer > 0)
                <a href="{{ route('reportes') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-cosechar-cream">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F1E6CE] text-[#B4812B]">
                        <x-icon name="clock" class="h-4 w-4" />
                    </span>
                    <span>
                        <span class="block font-medium text-cosechar-ink">{{ $this->porVencer }} {{ Str::plural('lote', $this->porVencer) }} por vencer</span>
                        <span class="block text-xs text-cosechar-muted">Ver en Reportes</span>
                    </span>
                </a>
            @endif
        @endif
    </div>
</div>
