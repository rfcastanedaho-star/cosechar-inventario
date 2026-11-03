@props(['producto'])
{{ $producto->codigo }} — {{ $producto->nombre }}@if ($producto->marca || $producto->presentacion)<span class="text-xs font-normal text-cosechar-muted"> · {{ collect([$producto->marca, $producto->presentacion])->filter()->join(' · ') }}</span>@endif@if ($producto->maneja_vencimiento)<span class="ml-1" title="Usa lotes" aria-label="Usa lotes">📦</span>@endif
