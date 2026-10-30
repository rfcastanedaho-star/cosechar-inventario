<?php

namespace App\Livewire;

use App\Exceptions\StockInsuficienteException;
use App\Livewire\Concerns\FiltraHistorial;
use App\Models\Lote;
use App\Models\StockMovimiento;
use App\Models\VentaDetalleLote;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\StockMovimientoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Control de lotes: se consultan con su estado y se ajustan (+ / −). Un ajuste no edita la
 * cantidad: crea un movimiento con su razón y responsable, que queda en el kardex del Inventario.
 * Los lotes nuevos no se crean aquí; nacen al registrar una compra.
 */
#[Layout('layouts.app')]
class LoteLista extends Component
{
    use FiltraHistorial;

    public ?int $ajustandoId = null;

    /** entrada = aumentar, salida = reducir. */
    public string $ajusteTipo = 'salida';

    public string $ajusteCantidad = '';

    public string $ajusteMotivo = '';

    public ?int $detalleId = null;

    public ?string $mensaje = null;

    protected function estadosPermitidos(): array
    {
        return array_keys(Lote::ESTADOS);
    }

    #[Computed]
    public function lotes(): LengthAwarePaginator
    {
        return app(LoteRepository::class)->historial($this->filtros());
    }

    /** @return array{con_stock: int, por_vencer: int, vencidos: int, agotados: int} */
    #[Computed]
    public function resumen(): array
    {
        return app(LoteRepository::class)->resumen();
    }

    #[Computed]
    public function loteEnAjuste(): ?Lote
    {
        return $this->ajustandoId ? Lote::with('producto')->find($this->ajustandoId) : null;
    }

    /** Razones que se pueden elegir según el tipo de ajuste. */
    #[Computed]
    public function razones(): array
    {
        $claves = $this->ajusteTipo === 'entrada' ? StockMovimiento::MOTIVOS_AUMENTAR : StockMovimiento::MOTIVOS_REDUCIR;

        return collect($claves)->mapWithKeys(fn ($clave) => [$clave => StockMovimiento::MOTIVOS[$clave]])->all();
    }

    /** Últimos movimientos del lote abierto en el detalle. */
    #[Computed]
    public function movimientosDelLote(): Collection
    {
        return $this->detalleId
            ? StockMovimiento::with('responsable')->where('lote_id', $this->detalleId)->orderByDesc('fecha')->orderByDesc('id')->limit(10)->get()
            : new Collection;
    }

    /**
     * Vendido y ajustes del lote abierto en el detalle (separados: una merma no es una venta).
     *
     * @return array{vendido: int, ajustes_menos: int, ajustes_mas: int}
     */
    #[Computed]
    public function totalesDelLote(): array
    {
        return $this->detalleId
            ? app(StockMovimientoRepository::class)->totalesPorLote([$this->detalleId])[$this->detalleId]
            : ['vendido' => 0, 'ajustes_menos' => 0, 'ajustes_mas' => 0];
    }

    /** Líneas de venta que consumieron unidades del lote abierto en el detalle. */
    #[Computed]
    public function ventasDelLote(): Collection
    {
        return $this->detalleId
            ? VentaDetalleLote::with('detalle.venta')->where('lote_id', $this->detalleId)->orderByDesc('id')->limit(10)->get()
            : new Collection;
    }

    public function verDetalle(int $id): void
    {
        $this->detalleId = $this->detalleId === $id ? null : $id;
    }

    public function iniciarAjuste(int $id, string $tipo): void
    {
        if (! in_array($tipo, ['entrada', 'salida'], true) || ! Lote::whereKey($id)->exists()) {
            return;
        }

        $this->resetErrorBag();
        $this->mensaje = null;
        $this->ajustandoId = $id;
        $this->ajusteTipo = $tipo;
        $this->ajusteCantidad = '';
        $this->ajusteMotivo = '';
    }

    public function cancelarAjuste(): void
    {
        $this->resetErrorBag();
        $this->reset(['ajustandoId', 'ajusteCantidad', 'ajusteMotivo']);
    }

    protected function rules(): array
    {
        $cantidad = ['required', 'integer', 'min:1'];

        if ($this->ajusteTipo === 'salida' && $this->loteEnAjuste) {
            $cantidad[] = 'max:'.$this->loteEnAjuste->cantidad;
        }

        return [
            'ajusteCantidad' => $cantidad,
            'ajusteMotivo' => ['required', Rule::in(array_keys($this->razones))],
        ];
    }

    protected function messages(): array
    {
        return [
            'ajusteCantidad.required' => 'Escriba la cantidad.',
            'ajusteCantidad.integer' => 'La cantidad debe ser un número entero.',
            'ajusteCantidad.min' => 'La cantidad debe ser mayor a cero.',
            'ajusteCantidad.max' => 'No puede reducir más de lo que hay en el lote.',
            'ajusteMotivo.required' => 'Elija la razón del ajuste.',
            'ajusteMotivo.in' => 'La razón elegida no corresponde a este ajuste.',
        ];
    }

    public function guardarAjuste(StockMovimientoService $servicio): void
    {
        $lote = $this->loteEnAjuste;

        if (! $lote) {
            return;
        }

        $data = $this->validate();

        try {
            $servicio->ajustarLote($lote, $this->ajusteTipo, (int) $data['ajusteCantidad'], $data['ajusteMotivo'], auth()->user());
        } catch (StockInsuficienteException $e) {
            $this->addError('ajusteCantidad', $e->getMessage());

            return;
        }

        $this->mensaje = ($this->ajusteTipo === 'entrada' ? 'Se aumentaron ' : 'Se redujeron ')."{$data['ajusteCantidad']} unidades del lote {$lote->numero_lote}.";
        $this->reset(['ajustandoId', 'ajusteCantidad', 'ajusteMotivo']);
        unset($this->lotes, $this->resumen);
    }

    public function render()
    {
        return view('livewire.lote-lista');
    }
}
