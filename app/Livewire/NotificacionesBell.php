<?php

namespace App\Livewire;

use App\Services\AlertaService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificacionesBell extends Component
{
    #[Computed]
    public function stockBajo(): int
    {
        return app(AlertaService::class)->productosConStockMinimo()->count();
    }

    #[Computed]
    public function porVencer(): int
    {
        return app(AlertaService::class)->lotesPorVencer()->count();
    }

    #[Computed]
    public function total(): int
    {
        return $this->stockBajo + $this->porVencer;
    }

    public function render()
    {
        return view('livewire.notificaciones-bell');
    }
}
