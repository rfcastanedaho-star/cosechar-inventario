@php
    $productos = fn (int $n) => $n === 1 ? '1 producto' : $n.' productos';
@endphp

<div class="mx-auto max-w-4xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-cosechar-ink">Categorías</h1>
            <p class="mt-1 text-sm text-cosechar-muted">Los productos se asignan a una subcategoría (o a un grupo que no tenga subcategorías).</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('productos.index') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
                Volver a productos
            </a>
            <a href="{{ route('categorias.nueva') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
                Registrar categoría
            </a>
        </div>
    </div>

    @if ($error)
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $error }}</div>
    @endif

    <div class="flex flex-col gap-4">
        @forelse ($this->grupos as $grupo)
            <div wire:key="grupo-{{ $grupo->id }}" class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
                <div class="flex items-center justify-between gap-3 bg-cosechar-cream px-4 py-3">
                    <div>
                        <span class="font-semibold text-cosechar-ink">{{ $grupo->nombre }}</span>
                        @if ($grupo->subcategorias->isEmpty())
                            <span class="ml-2 text-xs text-cosechar-muted">{{ $productos($grupo->productos_count) }}</span>
                        @else
                            <span class="ml-2 text-xs text-cosechar-muted">Grupo · {{ $grupo->subcategorias->count() }} subcategoría(s)</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 whitespace-nowrap text-xs font-medium">
                        @if ($grupo->productos_count === 0)
                            <a href="{{ route('categorias.nueva', ['grupo' => $grupo->id]) }}" class="text-cosechar-olive underline hover:text-cosechar-forest">Agregar subcategoría</a>
                        @endif
                        <a href="{{ route('categorias.editar', $grupo) }}" class="text-cosechar-olive underline hover:text-cosechar-forest">Renombrar</a>
                        <button
                            type="button"
                            wire:click="eliminar({{ $grupo->id }})"
                            wire:confirm="¿Eliminar «{{ $grupo->nombre }}»?"
                            class="text-cosechar-danger underline"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>

                @foreach ($grupo->subcategorias as $sub)
                    <div wire:key="sub-{{ $sub->id }}" class="flex items-center justify-between gap-3 border-t border-cosechar-border px-4 py-2.5 text-sm">
                        <div>
                            <span class="ml-4 text-cosechar-ink">{{ $sub->nombre }}</span>
                            <span class="ml-2 text-xs text-cosechar-muted">{{ $productos($sub->productos_count) }}</span>
                        </div>
                        <div class="flex items-center gap-3 whitespace-nowrap text-xs font-medium">
                            <a href="{{ route('categorias.editar', $sub) }}" class="text-cosechar-olive underline hover:text-cosechar-forest">Renombrar</a>
                            <button
                                type="button"
                                wire:click="eliminar({{ $sub->id }})"
                                wire:confirm="¿Eliminar «{{ $sub->nombre }}»?"
                                class="text-cosechar-danger underline"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-2xl border border-cosechar-border bg-white px-4 py-6 text-center text-sm text-cosechar-muted">
                Todavía no hay categorías.
            </div>
        @endforelse
    </div>
</div>
