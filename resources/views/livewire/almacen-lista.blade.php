<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Almacenes</h1>
        <a href="{{ route('almacenes.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar almacén
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Dirección</th>
                    <th class="px-4 py-3">Encargado</th>
                    <th class="px-4 py-3">Lotes almacenados</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->almacenes as $almacen)
                    <tr wire:key="almacen-{{ $almacen->id }}">
                        <td class="px-4 py-3 font-medium text-cosechar-ink">{{ $almacen->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $almacen->direccion ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $almacen->encargado ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $almacen->lotes_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-cosechar-muted">No hay almacenes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
