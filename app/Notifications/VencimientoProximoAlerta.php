<?php

namespace App\Notifications;

use App\Models\Lote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VencimientoProximoAlerta extends Notification
{
    use Queueable;

    public function __construct(public Lote $lote) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $producto = $this->lote->producto;
        $diasRestantes = now()->diffInDays($this->lote->fecha_vencimiento, false);

        $lineaVencimiento = $diasRestantes < 0
            ? 'Este lote venció hace '.abs($diasRestantes).' días.'
            : "Este lote vence en {$diasRestantes} días.";

        return (new MailMessage)
            ->subject("Vencimiento próximo: {$producto->nombre} — Lote {$this->lote->numero_lote}")
            ->greeting('Alerta de vencimiento próximo')
            ->line("El lote \"{$this->lote->numero_lote}\" del producto \"{$producto->nombre}\" (código {$producto->codigo}) tiene vencimiento próximo.")
            ->line("Fecha de vencimiento: {$this->lote->fecha_vencimiento->format('d/m/Y')}")
            ->line($lineaVencimiento)
            ->line("Cantidad disponible en este lote: {$this->lote->cantidad} {$producto->unidad_medida}");
    }
}
