<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Almacenes</h1>
        <a href="{{ route('almacenes.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar almacén
        </a>
    </div>

    @if ($errorEstado)
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $errorEstado }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Dirección</th>
                    <th class="px-4 py-3">Encargado</th>
                    <th class="px-4 py-3">Lotes almacenados</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->almacenes as $almacen)
                    <tr wire:key="almacen-{{ $almacen->id }}" class="{{ $almacen->activo ? '' : 'bg-cosechar-cream/60 text-cosechar-muted' }}">
                        <td class="px-4 py-3 font-medium {{ $almacen->activo ? 'text-cosechar-ink' : '' }}">{{ $almacen->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $almacen->direccion ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $almacen->encargado ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $almacen->lotes_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $almacen->activo ? 'bg-cosechar-olive/15 text-cosechar-olive' : 'bg-gray-200 text-gray-600' }}">
                                {{ $almacen->activo ? 'Activo' : 'Desactivado' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('almacenes.editar', $almacen) }}" class="text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">Editar</a>
                            @can('anular-registros')
                                <button
                                    type="button"
                                    wire:click="cambiarEstado({{ $almacen->id }})"
                                    class="ml-3 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink"
                                >
                                    {{ $almacen->activo ? 'Desactivar' : 'Activar' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">No hay almacenes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
