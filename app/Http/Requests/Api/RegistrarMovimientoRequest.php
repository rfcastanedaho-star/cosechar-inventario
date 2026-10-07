<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los campos de almacén y cliente son opcionales para no romper a quien
     * ya consume el contrato original (producto_id, cantidad, motivo).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'in:venta,merma,producto_danado,ajuste_inventario,transferencia'],
            'almacen_id' => ['nullable', 'integer', Rule::exists('almacenes', 'id')->where('activo', true)],
            'cliente_nombre' => ['nullable', 'string', 'max:150'],
            'cliente_documento' => ['nullable', 'string', 'max:20'],
            'numero_comprobante' => ['nullable', 'string', 'max:50'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
