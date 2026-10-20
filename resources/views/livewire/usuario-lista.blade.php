<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Usuarios</h1>
        <a href="{{ route('usuarios.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar usuario
        </a>
    </div>

    @if ($error)
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $error }}</div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @foreach ($this->usuarios as $usuario)
                    <tr wire:key="usuario-{{ $usuario->id }}" class="{{ $usuario->activo ? '' : 'bg-cosechar-cream/60 text-cosechar-muted' }}">
                        <td class="px-4 py-3 font-medium text-cosechar-ink">
                            {{ $usuario->name }}
                            @if ($usuario->is(auth()->user()))
                                <span class="ml-2 rounded-full bg-cosechar-olive/15 px-2 py-0.5 text-[11px] font-normal text-cosechar-olive">Tú</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $usuario->email }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $usuario->etiqueta_rol }}</td>
                        <td class="px-4 py-3">
                            @if ($usuario->activo)
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Activo</span>
                            @else
                                <span class="rounded-full bg-cosechar-border px-2 py-1 text-xs font-semibold text-cosechar-muted">Inactivo</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('usuarios.editar', $usuario) }}" class="text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">Editar</a>
                            @unless ($usuario->is(auth()->user()))
                                <button
                                    type="button"
                                    wire:click="cambiarEstado({{ $usuario->id }})"
                                    wire:confirm="{{ $usuario->activo ? '¿Desactivar a '.$usuario->name.'? Perderá el acceso de inmediato.' : '¿Reactivar a '.$usuario->name.'?' }}"
                                    class="ml-3 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink"
                                >
                                    {{ $usuario->activo ? 'Desactivar' : 'Reactivar' }}
                                </button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
