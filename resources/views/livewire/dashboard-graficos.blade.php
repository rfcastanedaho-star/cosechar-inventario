<div class="mx-auto max-w-6xl p-6" wire:poll.30s="actualizar">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
        <span class="text-xs text-gray-400">Se actualiza automáticamente cada 30 segundos</span>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-sm" x-data="graficoStockCategoria(@js($stockPorCategoria))" x-init="init()">
            <h2 class="mb-4 text-sm font-semibold text-gray-700">Stock por categoría</h2>
            <canvas x-ref="canvas" height="220"></canvas>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm" x-data="graficoMovimientos(@js($movimientos))" x-init="init()">
            <h2 class="mb-4 text-sm font-semibold text-gray-700">Movimientos (últimos 30 días)</h2>
            <canvas x-ref="canvas" height="220"></canvas>
        </div>
    </div>
</div>

<script>
    function graficoStockCategoria(datosIniciales) {
        return {
            chart: null,
            init() {
                this.chart = new Chart(this.$refs.canvas, {
                    type: 'bar',
                    data: {
                        labels: datosIniciales.labels,
                        datasets: [{
                            label: 'Stock actual',
                            data: datosIniciales.valores,
                            backgroundColor: '#166534',
                            borderRadius: 4,
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
                this.chart = new Chart(this.$refs.canvas, {
                    type: 'line',
                    data: {
                        labels: datosIniciales.labels,
                        datasets: [
                            {
                                label: 'Entradas',
                                data: datosIniciales.entradas,
                                borderColor: '#166534',
                                backgroundColor: '#16653420',
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Salidas',
                                data: datosIniciales.salidas,
                                borderColor: '#b91c1c',
                                backgroundColor: '#b91c1c20',
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
