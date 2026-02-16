<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SaldoEmpresaMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $cif;
    public string $razon;
    public string $saldoFormateado;

    /**
     * Create a new message instance.
     */
    public function __construct(string $cif, string $razon, string $saldoFormateado)
    {
        $this->cif = $cif;
        $this->razon = $razon;
        $this->saldoFormateado = $saldoFormateado;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Saldo disponible de la Empresa {$this->razon} ({$this->cif})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.saldo-empresa',
            with: [
                'cif' => $this->cif,
                'razon' => $this->razon,
                'saldoFormateado' => $this->saldoFormateado,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
