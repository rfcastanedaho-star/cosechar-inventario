<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Services\ImagenProductoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ProductoLista extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $fotoNueva = null;

    public ?int $productoFotoId = null;

    /** @var array<int, int> */
    public array $stockMinimoEdit = [];

    #[Url(as: 'buscar')]
    public string $buscar = '';

    public function mount(): void
    {
        $this->stockMinimoEdit = Producto::pluck('stock_minimo', 'id')->all();
    }

    #[Computed]
    public function productos(): Collection
    {
        $buscar = trim($this->buscar);

        return (new ProductoRepository)->conStockActual()
            ->when($buscar !== '', fn (Collection $productos) => $productos->filter(
                fn (Producto $producto) => str_contains(mb_strtolower($producto->nombre), mb_strtolower($buscar))
                    || str_contains(mb_strtolower($producto->codigo), mb_strtolower($buscar))
            ))
            ->sortBy('nombre')
            ->values();
    }

    /** Se ejecuta apenas termina de subirse el archivo elegido desde la fila de un producto. */
    public function updatedFotoNueva(ImagenProductoService $imagenes): void
    {
        if ($this->productoFotoId === null || $this->fotoNueva === null) {
            return;
        }

        $this->validate(
            ['fotoNueva' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
            [
                'fotoNueva.image' => 'El archivo debe ser una imagen.',
                'fotoNueva.mimes' => 'La foto debe ser JPG, PNG o WebP.',
                'fotoNueva.max' => 'La foto no puede pesar más de 4 MB.',
            ],
        );

        $imagenes->guardar(Producto::findOrFail($this->productoFotoId), $this->fotoNueva);

        $this->reset(['fotoNueva', 'productoFotoId']);
        unset($this->productos);
    }

    public function guardarStockMinimo(int $productoId): void
    {
        $this->authorize('editar-stock-minimo');

        $nuevoValor = $this->stockMinimoEdit[$productoId] ?? null;

        $this->validate([
            'stockMinimoEdit.'.$productoId => ['required', 'integer', 'min:0'],
        ], [
            'stockMinimoEdit.*.required' => 'El stock mínimo es obligatorio.',
            'stockMinimoEdit.*.integer' => 'Debe ser un número entero.',
            'stockMinimoEdit.*.min' => 'No puede ser negativo.',
        ]);

        $producto = Producto::findOrFail($productoId);
        (new ProductoRepository)->update($producto, ['stock_minimo' => $nuevoValor]);

        unset($this->productos);
    }

    public function render()
    {
        return view('livewire.producto-lista');
    }
}
