<?php

namespace App\Livewire;

use App\Models\Categoria;
use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Services\AuditoriaService;
use App\Services\ImagenProductoService;
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
        'stock_minimo', 'precio', 'maneja_vencimiento',
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
            $this->maneja_vencimiento = $producto->maneja_vencimiento;
            $this->marca = $producto->marca ?? '';
            $this->presentacion = $producto->presentacion ?? '';

            return;
        }

        $this->codigo = app(ProductoRepository::class)->siguienteCodigo();
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

    public function guardar(ProductoRepository $productos, ImagenProductoService $imagenes, AuditoriaService $auditoria): void
    {
        $data = $this->validate();
        unset($data['imagen']);

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

        $producto = $productos->create($data);

        if ($this->imagen) {
            $imagenes->guardar($producto, $this->imagen);
        }

        $this->reset(['codigo', 'nombre', 'marca', 'presentacion', 'categoria_id', 'unidad_medida', 'stock_minimo', 'precio', 'maneja_vencimiento', 'imagen']);
        $this->codigo = $productos->siguienteCodigo();
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.producto-form');
    }
}
