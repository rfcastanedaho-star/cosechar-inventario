@php
    $navActivos = [
        ['route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
        ['route' => 'productos.index', 'pattern' => 'productos.*', 'icon' => 'productos', 'label' => 'Productos'],
        ['route' => 'movimientos.nuevo', 'pattern' => 'movimientos.*', 'icon' => 'movimientos', 'label' => 'Movimientos'],
        ['route' => 'almacenes.index', 'pattern' => 'almacenes.*', 'icon' => 'almacenes', 'label' => 'Almacenes'],
        ['route' => 'proveedores.index', 'pattern' => 'proveedores.*', 'icon' => 'proveedores', 'label' => 'Proveedores'],
        ['route' => 'compras.index', 'pattern' => 'compras.*', 'icon' => 'compras', 'label' => 'Compras'],
        ['route' => 'ventas.index', 'pattern' => 'ventas.*', 'icon' => 'ventas', 'label' => 'Ventas'],
        ['route' => 'reportes', 'pattern' => 'reportes', 'icon' => 'reportes', 'label' => 'Reportes'],
    ];
@endphp

<aside class="flex w-64 shrink-0 flex-col gap-1 bg-cosechar-forest px-4 py-6">

    <div class="flex items-center gap-2.5 px-2 pb-6">
        <svg viewBox="0 0 24 24" width="24" height="24">
            <path d="M12 21V11" stroke="#CFE0C9" stroke-width="1.6" stroke-linecap="round" fill="none"/>
            <path d="M12 11C12 6.2 8.3 3.6 4 3.6 4 8.4 7.7 11 12 11z" fill="#8FBF6B"/>
            <path d="M12 11c0-3.6 2.9-5.6 6.2-5.6 0 3.7-2.5 5.6-6.2 5.6z" fill="#6FA355"/>
        </svg>
        <span class="font-sans text-[19px] font-semibold tracking-wide text-cosechar-cream">COSECHAR</span>
    </div>

    <nav class="flex flex-col gap-0.5">
        @foreach ($navActivos as $item)
            @php($activo = request()->routeIs($item['pattern']))
            <a
                href="{{ route($item['route']) }}"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ $activo ? 'bg-cosechar-cream font-semibold text-cosechar-forest' : 'font-medium text-cosechar-cream/80 hover:bg-white/5' }}"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $activo ? 'bg-cosechar-olive/20 text-cosechar-forest' : 'bg-white/8 text-cosechar-cream/80' }}">
                    <x-icon :name="$item['icon']" class="h-[18px] w-[18px]" />
                </span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <a
        href="{{ route('movimientos.nuevo') }}"
        class="mt-5 flex items-center gap-3 rounded-xl border border-dashed border-cosechar-cream/25 px-3 py-3 text-sm font-medium text-cosechar-cream/90 hover:bg-white/5"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
            <x-icon name="plus" class="h-[18px] w-[18px]" />
        </span>
        Registrar movimiento
    </a>

    <div class="mt-auto flex items-center gap-2.5 border-t border-white/10 pt-4">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cosechar-olive/30 font-sans text-[13px] font-semibold text-cosechar-cream">
            {{ Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}
        </span>
        <div class="min-w-0 leading-tight">
            <p class="truncate text-[13px] font-semibold text-cosechar-cream">{{ auth()->user()->name }}</p>
            <p class="text-[11px] uppercase tracking-wide text-cosechar-cream/55">{{ str_replace('_', ' ', auth()->user()->rol) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-cosechar-cream/70 hover:bg-white/5">
            <x-icon name="logout" class="h-[18px] w-[18px]" />
            Salir
        </button>
    </form>

</aside>
