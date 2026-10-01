<div class="flex items-center justify-between gap-4 border-b border-cosechar-border bg-white px-8 py-3">

    <form method="GET" action="{{ route('productos.index') }}" class="flex w-full max-w-md items-center gap-2 rounded-full border border-cosechar-border bg-cosechar-cream px-4 py-2">
        <x-icon name="search" class="h-4 w-4 shrink-0 text-cosechar-muted" />
        <input
            type="search"
            name="buscar"
            value="{{ request()->query('buscar') }}"
            placeholder="Buscar producto por nombre o código..."
            class="w-full border-0 bg-transparent p-0 text-sm text-cosechar-ink placeholder:text-cosechar-muted focus:ring-0"
        >
    </form>

    <div class="flex shrink-0 items-center gap-3">
        <livewire:notificaciones-bell />

        <div class="flex items-center gap-2.5 rounded-full border border-cosechar-border bg-white py-1 pl-1 pr-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-cosechar-forest font-sans text-[13px] font-semibold text-cosechar-cream">
                {{ Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}
            </span>
            <div class="leading-tight">
                <p class="text-[13px] font-semibold text-cosechar-ink">{{ auth()->user()->name }}</p>
                <p class="text-[11px] uppercase tracking-wide text-cosechar-muted">{{ str_replace('_', ' ', auth()->user()->rol) }}</p>
            </div>
        </div>
    </div>

</div>
