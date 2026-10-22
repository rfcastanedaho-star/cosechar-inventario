<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-cosechar-ink">Clientes</h1>
        <p class="mt-1 text-sm text-cosechar-muted">Los clientes se registran solos al registrar una venta con su documento. Aquí puede consultarlos y corregir sus datos.</p>
    </div>

    @include('livewire.partials.filtros-historial', [
        'placeholder' => 'Nombre, documento o teléfono...',
        'estados' => ['activo' => 'Activo', 'inactivo' => 'Desactivado'],
        'total' => $this->clientes->total(),
    ])

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Dirección</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->clientes as $cliente)
                    <tr wire:key="cliente-{{ $cliente->id }}" class="{{ $cliente->activo ? '' : 'bg-cosechar-cream/60 text-cosechar-muted' }}">
                        <td class="px-4 py-3 font-medium {{ $cliente->activo ? 'text-cosechar-ink' : '' }}">{{ $cliente->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $cliente->documento ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $cliente->telefono ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $cliente->direccion ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $cliente->activo ? 'bg-cosechar-olive/15 text-cosechar-olive' : 'bg-cosechar-border text-cosechar-muted' }}">
                                {{ $cliente->activo ? 'Activo' : 'Desactivado' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('clientes.editar', $cliente) }}" class="text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">Editar</a>
                            @can('anular-registros')
                                <button
                                    type="button"
                                    wire:click="cambiarEstado({{ $cliente->id }})"
                                    class="ml-3 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink"
                                >
                                    {{ $cliente->activo ? 'Desactivar' : 'Activar' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">
                            {{ $this->hayFiltros() ? 'No se encontraron clientes con esos filtros.' : 'No hay clientes registrados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('livewire.partials.paginacion', ['paginador' => $this->clientes])
</div>
