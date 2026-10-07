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
        <img src="{{ asset('images/icono-cosechar-badge.png') }}" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover">
        <span class="font-sans text-[21px] font-semibold tracking-wide text-cosechar-cream">COSECHAR</span>
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

    <form method="POST" action="{{ route('logout') }}" class="mt-auto border-t border-white/10 pt-4">
        @csrf
        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-cosechar-cream/70 hover:bg-white/5">
            <x-icon name="logout" class="h-[18px] w-[18px]" />
            Salir
        </button>
    </form>

</aside>
