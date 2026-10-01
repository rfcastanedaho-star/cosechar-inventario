<div class="mx-auto max-w-6xl p-8" wire:poll.30s="actualizar">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-cosechar-muted">{{ now()->locale('es')->translatedFormat('d \d\e F, Y') }}</p>
            <h1 class="font-sans text-2xl font-semibold text-cosechar-ink">Hola, {{ explode(' ', auth()->user()->name)[0] }}</h1>
        </div>
        <span class="text-xs text-cosechar-muted">Se actualiza automáticamente cada 30 segundos</span>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex flex-col gap-4 rounded-2xl border border-cosechar-border bg-white p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#EDE7DA] text-cosechar-muted">
                <x-icon name="productos" class="h-5 w-5" />
            </span>
            <div>
                <p class="mb-1 text-[13px] font-medium text-cosechar-muted">Total productos</p>
                <p class="font-sans text-[27px] font-semibold text-cosechar-ink">{{ $this->resumen['totalProductos'] }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl border border-cosechar-border bg-white p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F3E0DC] text-cosechar-danger">
                <x-icon name="warning" class="h-5 w-5" />
            </span>
            <div>
                <p class="mb-1 text-[13px] font-medium text-cosechar-muted">Stock bajo</p>
                <p class="font-sans text-[27px] font-semibold text-cosechar-danger">{{ $this->resumen['stockBajo'] }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl border border-cosechar-border bg-white p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F1E6CE] text-[#B4812B]">
                <x-icon name="clock" class="h-5 w-5" />
            </span>
            <div>
                <p class="mb-1 text-[13px] font-medium text-cosechar-muted">Por vencer</p>
                <p class="font-sans text-[27px] font-semibold text-[#B4812B]">{{ $this->resumen['porVencer'] }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl border border-cosechar-border bg-white p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cosechar-olive/15 text-cosechar-olive">
                <x-icon name="movimientos" class="h-5 w-5" />
            </span>
            <div>
                <p class="mb-1 text-[13px] font-medium text-cosechar-muted">Movs. hoy</p>
                <p class="font-sans text-[27px] font-semibold text-cosechar-olive">{{ $this->resumen['movimientosHoy'] }}</p>
            </div>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">

        @php
            $porcentaje = $this->saludStock['porcentaje'];
            $circunferencia = 2 * pi() * 45;
            $offset = $circunferencia - ($circunferencia * $porcentaje / 100);
        @endphp
        <div class="flex flex-col gap-5 rounded-2xl border border-cosechar-border bg-white p-6">
            <h2 class="text-sm font-semibold text-cosechar-ink">Salud del stock</h2>

            <div class="relative flex items-center justify-center">
                <svg viewBox="0 0 100 100" class="h-36 w-36 -rotate-90">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#EDE7DA" stroke-width="9" />
                    <circle
                        cx="50" cy="50" r="45" fill="none" stroke="#4C7A3B" stroke-width="9"
                        stroke-linecap="round"
                        stroke-dasharray="{{ $circunferencia }}"
                        stroke-dashoffset="{{ $offset }}"
                    />
                </svg>
                <div class="absolute flex flex-col items-center">
                    <span class="font-sans text-2xl font-semibold text-cosechar-ink">{{ $porcentaje }}%</span>
                    <span class="text-[11px] text-cosechar-muted">saludable</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 border-t border-cosechar-border pt-4 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-cosechar-muted">Lotes activos</span>
                    <span class="font-semibold text-cosechar-ink">{{ $this->saludStock['lotesActivos'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-cosechar-muted">Movimientos hoy</span>
                    <span class="font-semibold text-cosechar-ink">{{ $this->saludStock['movimientosHoy'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-cosechar-muted">Último movimiento</span>
                    <span class="font-semibold text-cosechar-ink">{{ $this->saludStock['ultimoMovimientoTexto'] }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-cosechar-border bg-white p-6 lg:col-span-2" x-data="graficoStockCategoria(@js($stockPorCategoria))" x-init="init()">
            <h2 class="mb-4 text-sm font-semibold text-cosechar-ink">Stock por categoría</h2>
            <canvas x-ref="canvas" height="170"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-cosechar-border bg-white p-6 lg:col-span-2" x-data="graficoMovimientos(@js($movimientos))" x-init="init()">
            <h2 class="mb-4 text-sm font-semibold text-cosechar-ink">Movimientos (últimos 30 días)</h2>
            <canvas x-ref="canvas" height="200"></canvas>
        </div>

        <div class="rounded-2xl border border-cosechar-border bg-white p-6">
            <h2 class="mb-4 text-sm font-semibold text-cosechar-ink">Próximos vencimientos</h2>

            @if ($this->proximosVencimientos->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                    <x-icon name="sprout" class="h-8 w-8 text-cosechar-muted" />
                    <p class="text-sm font-medium text-[#514C3F]">Nada por vencer pronto</p>
                    <p class="max-w-[200px] text-xs text-cosechar-muted">Los lotes próximos a vencer aparecerán aquí.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-cosechar-border">
                    @foreach ($this->proximosVencimientos as $lote)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-cosechar-ink">{{ $lote['producto'] }}</p>
                                <p class="text-xs text-cosechar-muted">{{ $lote['numeroLote'] }} · {{ $lote['cantidad'] }} uds.</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold {{ $lote['dias'] < 0 ? 'bg-[#F3E0DC] text-cosechar-danger' : 'bg-[#F1E6CE] text-[#B4812B]' }}">
                                {{ $lote['dias'] < 0 ? 'Vencido' : "{$lote['dias']} días" }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

<script>
    function graficoStockCategoria(datosIniciales) {
        return {
            chart: null,
            init() {
                Chart.getChart(this.$refs.canvas)?.destroy();

                this.chart = new Chart(this.$refs.canvas, {
                    type: 'bar',
                    data: {
                        labels: datosIniciales.labels,
                        datasets: [{
                            label: 'Stock actual',
                            data: datosIniciales.valores,
                            backgroundColor: ['#4C7A3B', '#C9972F', '#BA6B4A', '#7D7868'],
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } },
                    },
                });

                window.addEventListener('dashboard-actualizado', (evento) => {
                    const datos = evento.detail.stockPorCategoria;
                    this.chart.data.labels = datos.labels;
                    this.chart.data.datasets[0].data = datos.valores;
                    this.chart.update();
                });
            },
        };
    }

    function graficoMovimientos(datosIniciales) {
        return {
            chart: null,
            init() {
                Chart.getChart(this.$refs.canvas)?.destroy();

                this.chart = new Chart(this.$refs.canvas, {
                    type: 'line',
                    data: {
                        labels: datosIniciales.labels,
                        datasets: [
                            {
                                label: 'Entradas',
                                data: datosIniciales.entradas,
                                borderColor: '#4C7A3B',
                                backgroundColor: '#4C7A3B20',
                                pointBackgroundColor: '#4C7A3B',
                                pointBorderColor: '#fff',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Salidas',
                                data: datosIniciales.salidas,
                                borderColor: '#B4473E',
                                backgroundColor: '#B4473E20',
                                pointBackgroundColor: '#B4473E',
                                pointBorderColor: '#fff',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: 0.3,
                                fill: true,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } },
                    },
                });

                window.addEventListener('dashboard-actualizado', (evento) => {
                    const datos = evento.detail.movimientos;
                    this.chart.data.labels = datos.labels;
                    this.chart.data.datasets[0].data = datos.entradas;
                    this.chart.data.datasets[1].data = datos.salidas;
                    this.chart.update();
                });
            },
        };
    }
</script>
