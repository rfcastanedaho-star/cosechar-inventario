<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Filtros y paginación comunes de las pantallas de historial (compras, ventas,
 * proveedores y almacenes). Cada pantalla decide qué filtros muestra.
 */
trait FiltraHistorial
{
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public ?int $almacen_id = null;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    /** Valores válidos del filtro de estado; cada pantalla puede cambiarlos. */
    protected function estadosPermitidos(): array
    {
        return ['vigente', 'anulada'];
    }

    /**
     * @return array{buscar: string, estado: ?string, almacen_id: ?int, desde: string, hasta: string}
     */
    protected function filtros(): array
    {
        return [
            'buscar' => $this->buscar,
            'estado' => in_array($this->estado, $this->estadosPermitidos(), true) ? $this->estado : null,
            'almacen_id' => $this->almacen_id,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];
    }

    public function hayFiltros(): bool
    {
        return trim($this->buscar) !== '' || $this->estado !== '' || $this->almacen_id || $this->desde !== '' || $this->hasta !== '';
    }

    /** Al cambiar cualquier filtro se vuelve a la primera página. */
    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function updatedAlmacenId(): void
    {
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado', 'almacen_id', 'desde', 'hasta']);
        $this->resetPage();
    }
}
