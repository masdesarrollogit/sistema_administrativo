<?php

namespace App\Console\Commands;

use App\Models\EncuestaCalidad;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

/**
 * Lee el buzón IMAP donde Power Automate deja los correos con las respuestas del
 * cuestionario de calidad (vía Office 365 Outlook, conector estándar gratis).
 *
 * Cada correo trae un bloque JSON entre marcadores con un `token` compartido.
 * El comando valida el token, mapea el JSON y persiste la respuesta (idempotente
 * por forms_id). Es la vía "en tiempo real" sin coste (sin acción HTTP Premium).
 */
class LeerEncuestasImap extends Command
{
    protected $signature = 'encuestas-calidad:leer-imap {--dry-run : Procesa pero no marca los correos como leídos}';

    protected $description = 'Lee por IMAP los correos de Power Automate con respuestas del cuestionario de calidad FUNDAE';

    public function handle(EncuestaCalidadService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!config('encuesta_calidad.imap_enabled')) {
            $this->warn('⏸ Lectura IMAP DESACTIVADA (encuesta_calidad.imap_enabled = false). No se toca el buzón.');
            return self::SUCCESS;
        }

        $marca = (string) config('encuesta_calidad.asunto_marca', '[ENCUESTA-CALIDAD]');
        $cuenta = (string) config('encuesta_calidad.imap_account', 'encuestas');

        $procesados = 0;
        $ignorados = 0;
        $errores = 0;

        try {
            $client = Client::account($cuenta);
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $mensajes = $folder->query()->unseen()->leaveUnread()->get();

            $this->info("📬 Correos no leídos en INBOX: " . $mensajes->count());

            foreach ($mensajes as $mensaje) {
                $asunto = (string) $mensaje->getSubject();
                if (!str_contains($asunto, $marca)) {
                    continue; // no es un correo de encuesta
                }

                $cuerpo = trim((string) ($mensaje->getTextBody() ?: strip_tags((string) $mensaje->getHTMLBody())));
                $from = optional($mensaje->getFrom()[0] ?? null)->mail;

                $res = $this->procesarCuerpo($cuerpo, $from, $service);

                if ($res['ok']) {
                    $procesados++;
                    if (!$dryRun) {
                        $mensaje->setFlag('Seen');
                    }
                    $this->line("  ✅ {$res['motivo']}");
                } else {
                    $ignorados++;
                    $this->warn("  ⚠️ Ignorado: {$res['motivo']}");
                    Log::warning('encuestas-calidad:leer-imap ignoró un correo', ['motivo' => $res['motivo'], 'from' => $from]);
                }
            }
        } catch (\Throwable $e) {
            $errores++;
            $this->error('❌ Error IMAP: ' . $e->getMessage());
            Log::error('encuestas-calidad:leer-imap error', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info("Resumen — procesados: {$procesados} · ignorados: {$ignorados} · errores: {$errores}" . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /**
     * Procesa el cuerpo de un correo: valida remitente + token, extrae el JSON y
     * persiste la respuesta. Público y sin IMAP para poder testearlo con fixtures.
     *
     * @return array{ok: bool, motivo: string, encuesta?: EncuestaCalidad}
     */
    public function procesarCuerpo(string $cuerpo, ?string $from, EncuestaCalidadService $service): array
    {
        // 1) Remitente esperado (opcional)
        $remitente = config('encuesta_calidad.remitente_esperado');
        if ($remitente && strcasecmp((string) $from, (string) $remitente) !== 0) {
            return ['ok' => false, 'motivo' => "remitente no autorizado ({$from})"];
        }

        // 2) Extraer bloque JSON entre marcadores
        $ini = (string) config('encuesta_calidad.marcador_inicio', '===ENCUESTA-CALIDAD-JSON===');
        $fin = (string) config('encuesta_calidad.marcador_fin', '===FIN===');
        $patron = '/' . preg_quote($ini, '/') . '(.*?)' . preg_quote($fin, '/') . '/s';
        if (!preg_match($patron, $cuerpo, $m)) {
            return ['ok' => false, 'motivo' => 'sin bloque JSON'];
        }

        $json = json_decode(trim($m[1]), true);
        if (!is_array($json)) {
            return ['ok' => false, 'motivo' => 'JSON inválido'];
        }

        // 3) Validar token compartido
        $tokenEsperado = config('encuesta_calidad.token');
        if (empty($tokenEsperado) || ($json['token'] ?? null) !== $tokenEsperado) {
            return ['ok' => false, 'motivo' => 'token inválido'];
        }

        // 4) Mapear JSON → datos del modelo
        $datos = $this->jsonADatos($json, $service);

        if (empty($datos['forms_id'])) {
            return ['ok' => false, 'motivo' => 'falta forms_id'];
        }

        $encuesta = $service->guardarRespuesta($datos, 'power_automate');

        return ['ok' => true, 'motivo' => "guardada respuesta {$datos['forms_id']}", 'encuesta' => $encuesta];
    }

    /** Convierte el JSON de Power Automate en el array de campos del modelo. */
    protected function jsonADatos(array $json, EncuestaCalidadService $service): array
    {
        $s = fn ($k) => isset($json[$k]) && trim((string) $json[$k]) !== '' ? trim((string) $json[$k]) : null;

        $datos = [
            'forms_id'              => $s('forms_id'),
            'fecha_cumplimentacion' => $service->parsearFecha($s('fecha') ?? $s('fecha_cumplimentacion')),
            'hora_inicio'           => $service->parsearFecha($s('hora_inicio')),
            'hora_fin'              => $service->parsearFecha($s('hora_fin')),
            'alumno_nombre'         => $s('alumno_nombre'),
            'alumno_email'          => $s('alumno_email') ? mb_strtolower($s('alumno_email')) : null,
            'numero_accion'         => $s('numero_accion') ? (int) preg_replace('/\D/', '', $s('numero_accion')) : null,
            'numero_grupo'          => $s('numero_grupo'),
            'cif_empresa'           => $s('cif_empresa') ? strtoupper($s('cif_empresa')) : null,
            'denominacion_accion'   => $s('denominacion_accion'),
            'modalidad'             => $s('modalidad'),
            'observaciones'         => $s('observaciones'),
            'satisfaccion_general'  => $this->likert($json['satisfaccion_general'] ?? $json['item_10_satisfaccion'] ?? null),
        ];

        for ($i = 1; $i <= 19; $i++) {
            $item = 'item_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $datos[$item] = $this->likert($json[$item] ?? null);
        }

        return $datos;
    }

    protected function likert($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (preg_match('/[1-4]/', (string) $v, $m)) {
            return (int) $m[0];
        }
        return null;
    }
}
