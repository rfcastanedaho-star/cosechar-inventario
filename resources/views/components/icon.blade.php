@props(['name'])

@php($base = 'fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"')

<span {{ $attributes }}>
    @switch($name)
        @case('dashboard')
            <svg viewBox="0 0 24 24" {!! $base !!}><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="4.5" rx="1.5"/><rect x="13.5" y="10.5" width="7" height="10" rx="1.5"/><rect x="3.5" y="13" width="7" height="7.5" rx="1.5"/></svg>
            @break

        @case('productos')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M4 8.5 12 4l8 4.5v8L12 21l-8-4.5z"/><path d="M4 8.5 12 13l8-4.5"/><path d="M12 13v8"/><path d="M10.3 5.8c0 1 .7 1.6 1.7 1.6M13.7 5.8c0 1-.7 1.6-1.7 1.6"/></svg>
            @break

        @case('movimientos')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M4 9h13"/><path d="M14 5l3.5 4L14 13"/><path d="M20 15H7"/><path d="M10 19l-3.5-4L10 11"/></svg>
            @break

        @case('reportes')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M6 3.5h8l4 4V20a.6.6 0 0 1-.6.6H6.6A.6.6 0 0 1 6 20z"/><path d="M14 3.5V8h4"/><path d="M8.5 16l2-2.5 2 1.8 3-3.8"/></svg>
            @break

        @case('almacenes')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/></svg>
            @break

        @case('proveedores')
            <svg viewBox="0 0 24 24" {!! $base !!}><rect x="2.5" y="7" width="10" height="8" rx="1"/><path d="M12.5 10h4l3 3v2h-7z"/><circle cx="7" cy="17.3" r="1.6"/><circle cx="16.5" cy="17.3" r="1.6"/></svg>
            @break

        @case('compras')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M4 10.5 12 6l8 4.5v7L12 22l-8-4.5z"/><path d="M12 2.5v8"/><path d="M9 7.5 12 10.5 15 7.5"/></svg>
            @break

        @case('ventas')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M3 4h2l1.6 10.2A1.6 1.6 0 0 0 8.2 15.6h8.4a1.6 1.6 0 0 0 1.6-1.3L19.5 8H6"/><circle cx="9" cy="19" r="1.3"/><circle cx="16" cy="19" r="1.3"/></svg>
            @break

        @case('search')
            <svg viewBox="0 0 24 24" {!! $base !!}><circle cx="10.5" cy="10.5" r="6.5"/><path d="M19.5 19.5 15.2 15.2"/></svg>
            @break

        @case('bell')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M6 10.5a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 14.5 6 10.5z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>
            @break

        @case('plus')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M12 5v14M5 12h14"/></svg>
            @break

        @case('logout')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M9 4H6a1.5 1.5 0 0 0-1.5 1.5v13A1.5 1.5 0 0 0 6 20h3"/><path d="M14 16.5 19 12l-5-4.5"/><path d="M19 12H9"/></svg>
            @break

        @case('warning')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M12 4 21 19H3z"/><path d="M12 10v4"/><circle cx="12" cy="16.6" r="0.9" fill="currentColor" stroke="none"/></svg>
            @break

        @case('clock')
            <svg viewBox="0 0 24 24" {!! $base !!}><circle cx="12" cy="12" r="8.2"/><path d="M12 7.5V12l3 2"/></svg>
            @break

        @case('sprout')
            <svg viewBox="0 0 24 24" {!! $base !!}><path d="M12 21V11"/><path d="M12 11C12 6.5 8.5 4 4.5 4 4.5 8.5 8 11 12 11z"/><path d="M12 11c0-3.5 2.8-5.5 6-5.5 0 3.6-2.4 5.5-6 5.5z"/></svg>
            @break
    @endswitch
</span>
