<?php

namespace App\Livewire;

use App\Models\Categoria;
use App\Repositories\AuditoriaRepository;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditoriaLista extends Component
{
    use WithPagination;

    #[Url]
    public string $entidad = '';

    #[Url]
    public ?int $usuario_id = null;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(): void
    {
        $this->authorize('ver-auditoria');
    }

    /** @return array<string, string> clave corta => nombre legible */
    #[Computed]
    public function entidades(): array
    {
        return collect(AuditoriaEtiquetas::ENTIDADES)
            ->map(fn (string $clase) => AuditoriaEtiquetas::entidad($clase))
            ->all();
    }

    #[Computed]
    public function usuarios(): Collection
    {
        return app(AuditoriaRepository::class)->usuariosQueCambiaron();
    }

    #[Computed]
    public function cambios(): LengthAwarePaginator
    {
        return app(AuditoriaRepository::class)->historial([
            'entidad' => AuditoriaEtiquetas::ENTIDADES[$this->entidad] ?? null,
            'user_id' => $this->usuario_id,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ]);
    }

    /** Nombres de categoría de la página actual, para mostrar categoria_id como texto. */
    #[Computed]
    public function categorias(): array
    {
        $ids = collect($this->cambios->items())
            ->where('campo', 'categoria_id')
            ->flatMap(fn ($cambio) => [$cambio->valor_anterior, $cambio->valor_nuevo])
            ->filter()
            ->unique()
            ->all();

        return $ids === [] ? [] : Categoria::whereIn('id', $ids)->pluck('nombre', 'id')->all();
    }

    /** Al cambiar cualquier filtro se vuelve a la primera página. */
    public function updated(string $property): void
    {
        if (in_array($property, ['entidad', 'usuario_id', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['entidad', 'usuario_id', 'desde', 'hasta']);
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.auditoria-lista');
    }
}
