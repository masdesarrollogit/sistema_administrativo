<?php

namespace App\Mail;

use App\Models\Candidato;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RecordatorioRequisitosMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Candidato $candidato,
        public Collection $requisitosFaltantes
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('candidatos.notificaciones.asunto_recordatorio', 'Recordatorio: Requisitos pendientes para tu curso'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.recordatorio-requisitos',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // 1. Adjuntar archivos específicos del candidato
        foreach ($this->candidato->archivos as $archivo) {
            $path = storage_path('app/public/' . $archivo->ruta);
            if (file_exists($path)) {
                $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path)
                    ->as($archivo->nombre)
                    ->withMime($archivo->mime_type);
            }
        }

        // 2. Adjuntar archivos generales de requisitos faltantes
        foreach ($this->requisitosFaltantes as $requisito) {
            $codigo = $requisito->tipoRequisito->codigo;
            $path = storage_path("app/requisitos/adjuntos/{$codigo}");
            
            $extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'];
            
            foreach ($extensions as $ext) {
                $fullPath = "{$path}.{$ext}";
                if (file_exists($fullPath)) {
                    $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($fullPath)
                        ->as($requisito->tipoRequisito->nombre . '.' . $ext);
                    break;
                }
            }
        }

        return $attachments;
    }
}
