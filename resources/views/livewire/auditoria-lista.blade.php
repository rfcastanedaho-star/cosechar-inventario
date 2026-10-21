@php
    use App\Support\AuditoriaEtiquetas as Etiqueta;

    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
    $hayFiltros = $entidad !== '' || $usuario_id || $desde !== '' || $hasta !== '';
@endphp

<div class="mx-auto max-w-6xl p-8">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-cosechar-ink">Historial de cambios</h1>
        <p class="mt-1 text-sm text-cosechar-muted">Quién cambió qué y cuándo en productos, almacenes, proveedores y usuarios.</p>
    </div>

    <div class="mb-4 grid gap-3 rounded-2xl border border-cosechar-border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="entidad" class="mb-1 block text-xs font-medium text-cosechar-muted">Qué se cambió</label>
            <select id="entidad" wire:model.live="entidad" class="{{ $campo }}">
                <option value="">Todo</option>
                @foreach ($this->entidades as $clave => $nombre)
                    <option value="{{ $clave }}">{{ $nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="usuario_id" class="mb-1 block text-xs font-medium text-cosechar-muted">Quién lo cambió</label>
            <select id="usuario_id" wire:model.live="usuario_id" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach ($this->usuarios as $usuario)
                    <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="desde" class="mb-1 block text-xs font-medium text-cosechar-muted">Desde</label>
            <input type="date" id="desde" wire:model.live="desde" class="{{ $campo }}">
        </div>

        <div>
            <label for="hasta" class="mb-1 block text-xs font-medium text-cosechar-muted">Hasta</label>
            <input type="date" id="hasta" wire:model.live="hasta" class="{{ $campo }}">
        </div>
    </div>

    @if ($hayFiltros)
        <p class="mb-3 text-sm text-cosechar-muted">
            {{ $this->cambios->total() }} resultado(s) ·
            <button type="button" wire:click="limpiarFiltros" class="font-medium text-cosechar-olive underline">Quitar filtros</button>
        </p>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Responsable</th>
                    <th class="px-4 py-3">Registro</th>
                    <th class="px-4 py-3">Dato</th>
                    <th class="px-4 py-3">Antes</th>
                    <th class="px-4 py-3">Después</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->cambios as $cambio)
                    <tr wire:key="cambio-{{ $cambio->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-cosechar-muted">{{ $cambio->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-cosechar-ink">{{ $cambio->usuario?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="block text-xs text-cosechar-muted">{{ Etiqueta::entidad($cambio->auditable_type) }}</span>
                            <span class="font-medium text-cosechar-ink">{{ Etiqueta::registro($cambio->auditable) }}</span>
                        </td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ Etiqueta::campo($cambio->campo) }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ Etiqueta::valor($cambio->campo, $cambio->valor_anterior, $this->categorias) }}</td>
                        <td class="px-4 py-3 font-medium text-cosechar-ink">{{ Etiqueta::valor($cambio->campo, $cambio->valor_nuevo, $this->categorias) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">
                            {{ $hayFiltros ? 'No se encontraron cambios con esos filtros.' : 'Todavía no hay cambios registrados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->cambios->total() > 0)
        <div class="mt-4 flex items-center justify-between text-sm text-cosechar-muted">
            <p>Mostrando {{ $this->cambios->firstItem() }}–{{ $this->cambios->lastItem() }} de {{ $this->cambios->total() }}</p>
            <div class="flex gap-2">
                <button
                    type="button"
                    wire:click="previousPage"
                    @disabled($this->cambios->onFirstPage())
                    class="rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    @disabled(! $this->cambios->hasMorePages())
                    class="rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Siguiente
                </button>
            </div>
        </div>
    @endif
</div>
