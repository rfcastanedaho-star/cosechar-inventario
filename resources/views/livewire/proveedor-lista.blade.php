<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-cosechar-ink">Proveedores</h1>
            <p class="mt-1 text-sm text-cosechar-muted">Registre aquí a sus proveedores: en Compras solo se elige entre los registrados. También puede consultarlos y corregir sus datos.</p>
        </div>
        <a href="{{ route('proveedores.nuevo') }}" class="shrink-0 rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">Registrar proveedor</a>
    </div>

    @if (session('ok'))
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">{{ session('ok') }}</div>
    @endif
    @include('livewire.partials.filtros-historial', [
        'placeholder' => 'Nombre o RUC...',
        'estados' => ['activo' => 'Activo', 'inactivo' => 'Desactivado'],
        'total' => $this->proveedores->total(),
    ])

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Nombre / Razón social</th>
                    <th class="px-4 py-3">RUC</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Dirección</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->proveedores as $proveedor)
                    <tr wire:key="proveedor-{{ $proveedor->id }}" class="{{ $proveedor->activo ? '' : 'bg-cosechar-cream/60 text-cosechar-muted' }}">
                        <td class="px-4 py-3 font-medium {{ $proveedor->activo ? 'text-cosechar-ink' : '' }}">{{ $proveedor->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->ruc ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->telefono ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->direccion ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $proveedor->correo ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $proveedor->activo ? 'bg-cosechar-olive/15 text-cosechar-olive' : 'bg-cosechar-border text-cosechar-muted' }}">
                                {{ $proveedor->activo ? 'Activo' : 'Desactivado' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('proveedores.editar', $proveedor) }}" class="text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">Editar</a>
                            @can('anular-registros')
                                <button
                                    type="button"
                                    wire:click="cambiarEstado({{ $proveedor->id }})"
                                    class="ml-3 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink"
                                >
                                    {{ $proveedor->activo ? 'Desactivar' : 'Activar' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-cosechar-muted">{{ $this->hayFiltros() ? 'No se encontraron proveedores con esos filtros.' : 'No hay proveedores registrados.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('livewire.partials.paginacion', ['paginador' => $this->proveedores])
</div>
