<?php

namespace App\Notifications;

use App\Models\Producto;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockMinimoAlerta extends Notification
{
    use Queueable;

    public function __construct(public Producto $producto) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stockActual = $this->producto->stock_actual ?? 0;

        return (new MailMessage)
            ->subject("Stock mínimo: {$this->producto->nombre}")
            ->greeting('Alerta de stock mínimo')
            ->line("El producto \"{$this->producto->nombre}\" (código {$this->producto->codigo}) está por debajo del stock mínimo configurado.")
            ->line("Stock actual: {$stockActual} {$this->producto->unidad_medida}")
            ->line("Stock mínimo configurado: {$this->producto->stock_minimo} {$this->producto->unidad_medida}")
            ->line('Se recomienda reponer este producto lo antes posible.');
    }
}
