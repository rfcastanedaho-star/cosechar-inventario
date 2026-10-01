<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Proveedores</h1>
        <a href="{{ route('proveedores.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar proveedor
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Nombre / Razón social</th>
                    <th class="px-4 py-3">RUC</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Dirección</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->proveedores as $proveedor)
                    <tr wire:key="proveedor-{{ $proveedor->id }}">
                        <td class="px-4 py-3 font-medium text-cosechar-ink">{{ $proveedor->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->ruc ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->telefono ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->direccion ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-cosechar-muted">No hay proveedores registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
