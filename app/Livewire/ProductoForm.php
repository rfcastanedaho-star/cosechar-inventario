<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Services\AuditoriaService;
use App\Services\ImagenProductoService;
use App\Services\ProductoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ProductoForm extends Component
{
    use WithFileUploads;

    /** Campos que identifican o valorizan al producto: al editar, solo el administrador los cambia. */
    private const CAMPOS_SENSIBLES = [
        'codigo', 'marca', 'presentacion', 'categoria_id', 'unidad_medida',
        'stock_minimo', 'precio', 'precio_costo', 'maneja_vencimiento',
    ];

    public ?int $productoId = null;

    /** @var TemporaryUploadedFile|null */
    public $imagen = null;

    public string $codigo = '';

    public string $nombre = '';

    public string $marca = '';

    public string $presentacion = '';

    public ?int $categoria_id = null;

    public string $unidad_medida = '';

    public int $stock_minimo = 0;

    public string $precio = '';

    public string $precio_costo = '';

    /** Solo al registrar: unidades que ya hay en el almacén, con su vencimiento si el producto lo maneja. */
    public string $stock_inicial = '';

    public string $vencimiento_inicial = '';

    public ?int $almacen_id = null;

    public bool $maneja_vencimiento = false;

    public bool $creado = false;

    public function mount(?Producto $producto = null): void
    {
        if ($producto?->exists) {
            $this->productoId = $producto->id;
            $this->codigo = $producto->codigo;
            $this->nombre = $producto->nombre;
            $this->categoria_id = $producto->categoria_id;
            $this->unidad_medida = $producto->unidad_medida;
            $this->stock_minimo = $producto->stock_minimo;
            $this->precio = (string) $producto->precio;
            $this->precio_costo = $producto->precio_costo !== null ? (string) $producto->precio_costo : '';
            $this->maneja_vencimiento = $producto->maneja_vencimiento;
            $this->marca = $producto->marca ?? '';
            $this->presentacion = $producto->presentacion ?? '';

            return;
        }

        $this->codigo = app(ProductoRepository::class)->siguienteCodigo();
        $this->almacen_id = $this->almacenUnico();
    }

    /** Con un solo almacén activo no hay nada que elegir. */
    private function almacenUnico(): ?int
    {
        return $this->almacenes->count() === 1 ? $this->almacenes->first()->id : null;
    }

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    /** ¿Se está registrando mercadería que ya existe? */
    #[Computed]
    public function tieneStockInicial(): bool
    {
        return ! $this->editando && is_numeric($this->stock_inicial) && (int) $this->stock_inicial > 0;
    }

    #[Computed]
    public function editando(): bool
    {
        return $this->productoId !== null;
    }

    /** Al registrar se llenan todos los campos; al editar, solo el administrador toca los sensibles. */
    #[Computed]
    public function puedeEditarSensibles(): bool
    {
        return ! $this->editando || Gate::allows('editar-datos-sensibles');
    }

    /** Con lotes registrados, cambiar entre FEFO y FIFO dejaría lotes sin fecha: se bloquea. */
    #[Computed]
    public function venceBloqueado(): bool
    {
        return $this->editando && Producto::findOrFail($this->productoId)->lotes()->exists();
    }

    protected function rules(): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:150'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        if (! $this->puedeEditarSensibles) {
            return $reglas;
        }

        if (! $this->editando) {
            $reglas += [
                'stock_inicial' => ['nullable', 'integer', 'min:0'],
                'vencimiento_inicial' => [Rule::requiredIf($this->tieneStockInicial && $this->maneja_vencimiento), 'nullable', 'date'],
                'almacen_id' => [Rule::requiredIf($this->tieneStockInicial), 'nullable', Rule::exists('almacenes', 'id')->where('activo', true)],
            ];
        }

        return $reglas + [
            'codigo' => ['required', 'string', 'max:50', Rule::unique('productos', 'codigo')->ignore($this->productoId)],
            'categoria_id' => [
                'required',
                // Solo se permiten categorías "hoja": una categoría con subcategorías
                // (p. ej. Agroquímicos) no es un valor válido, el producto debe
                // asignarse a la subcategoría correspondiente (p. ej. Fungicida).
                Rule::exists('categorias', 'id')->where(function ($query) {
                    $query->whereNotIn('id', function ($sub) {
                        $sub->select('categoria_padre_id')
                            ->from('categorias')
                            ->whereNotNull('categoria_padre_id');
                    });
                }),
            ],
            'unidad_medida' => ['required', 'in:saco,kg,litro,galon,unidad'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'precio' => ['required', 'numeric', 'min:0'],
            'precio_costo' => ['required', 'numeric', 'min:0'],
            'marca' => ['nullable', 'string', 'max:100'],
            'presentacion' => ['nullable', 'string', 'max:100'],
            'maneja_vencimiento' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un producto registrado con este código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'categoria_id.required' => 'Debe seleccionar una categoría.',
            'categoria_id.exists' => 'Seleccione una categoría o subcategoría válida (no un grupo con subcategorías).',
            'unidad_medida.required' => 'Debe seleccionar una unidad de medida.',
            'stock_minimo.required' => 'El stock mínimo es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio_costo.required' => 'El precio de costo es obligatorio.',
            'precio_costo.numeric' => 'El precio de costo debe ser un número válido.',
            'stock_inicial.integer' => 'El stock inicial debe ser un número entero.',
            'stock_inicial.min' => 'El stock inicial no puede ser negativo.',
            'vencimiento_inicial.required' => 'Indique cuándo vence el stock inicial.',
            'almacen_id.required' => 'Seleccione el almacén donde está el stock inicial.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'imagen.max' => 'La foto no puede pesar más de 4 MB.',
        ];
    }

    #[Computed]
    public function categoriasAgrupadas(): Collection
    {
        return Categoria::with(['subcategorias' => fn ($query) => $query->orderBy('nombre')])
            ->whereNull('categoria_padre_id')
            ->orderBy('nombre')
            ->get();
    }

    public function updated(string $property): void
    {
        if (! $this->puedeEditarSensibles && in_array($property, self::CAMPOS_SENSIBLES, true)) {
            return;
        }

        $this->validateOnly($property);
    }

    public function guardar(ProductoRepository $productos, ProductoService $servicio, ImagenProductoService $imagenes, AuditoriaService $auditoria): void
    {
        $data = $this->validate();
        unset($data['imagen']);

        // El stock inicial no es un dato del producto: se registra aparte, como su primer lote.
        $stockInicial = $this->tieneStockInicial
            ? ['cantidad' => (int) $data['stock_inicial'], 'almacen_id' => (int) $data['almacen_id'], 'fecha_vencimiento' => $data['vencimiento_inicial'] ?: null]
            : null;
        unset($data['stock_inicial'], $data['vencimiento_inicial'], $data['almacen_id']);

        // Los campos opcionales vacíos se guardan como nulos.
        foreach (['marca', 'presentacion'] as $opcional) {
            if (array_key_exists($opcional, $data) && ($data[$opcional] === '' || $data[$opcional] === null)) {
                $data[$opcional] = null;
            }
        }

        if ($this->editando) {
            $producto = Producto::findOrFail($this->productoId);

            if ($this->venceBloqueado) {
                unset($data['maneja_vencimiento']);
            }

            $auditoria->actualizar($producto, $data, auth()->user());

            if ($this->imagen) {
                $imagenes->guardar($producto, $this->imagen);
                $this->reset('imagen');
            }

            $this->maneja_vencimiento = $producto->fresh()->maneja_vencimiento;
            $this->creado = true;

            return;
        }

        $producto = $servicio->registrar($data, $stockInicial, auth()->user());

        if ($this->imagen) {
            $imagenes->guardar($producto, $this->imagen);
        }

        $this->reset(['codigo', 'nombre', 'marca', 'presentacion', 'categoria_id', 'unidad_medida', 'stock_minimo', 'precio', 'precio_costo', 'stock_inicial', 'vencimiento_inicial', 'maneja_vencimiento', 'imagen']);
        $this->almacen_id = $this->almacenUnico();
        $this->codigo = $productos->siguienteCodigo();
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.producto-form');
    }
}
