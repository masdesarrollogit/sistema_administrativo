<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SaldoParticipanteBonificadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $cif;
    public string $razon;
    public string $saldoFormateado;

    public function __construct(string $cif, string $razon, string $saldoFormateado)
    {
        $this->cif             = $cif;
        $this->razon           = $razon;
        $this->saldoFormateado = $saldoFormateado;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Saldo disponible - {$this->razon} ({$this->cif})",
            cc: [
                new Address('administracion@webcurso.es', 'Administración'),
                new Address('prospectos@webcurso.es', 'Prospectos'),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.saldo-participante-bonificado',
            with: [
                'cif'             => $this->cif,
                'razon'           => $this->razon,
                'saldoFormateado' => $this->saldoFormateado,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
