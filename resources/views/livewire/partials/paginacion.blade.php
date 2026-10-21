{{-- Pie de tabla con resumen y botones Anterior / Siguiente. Variable: $paginador --}}
@if ($paginador->total() > 0)
    <div class="mt-4 flex items-center justify-between text-sm text-cosechar-muted">
        <p>Mostrando {{ $paginador->firstItem() }}–{{ $paginador->lastItem() }} de {{ $paginador->total() }}</p>
        <div class="flex gap-2">
            <button
                type="button"
                wire:click="previousPage"
                @disabled($paginador->onFirstPage())
                class="rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
            >
                Anterior
            </button>
            <button
                type="button"
                wire:click="nextPage"
                @disabled(! $paginador->hasMorePages())
                class="rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
            >
                Siguiente
            </button>
        </div>
    </div>
@endif
