<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Una segunda oportunidad para terminar tu curso</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #7f1d1d; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">WebCurso</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.95;">
            Una segunda oportunidad para terminar tu curso
        </p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            Te escribimos porque el curso <strong>{{ $cursoNombre }}</strong> ha finalizado
            sin que hayas podido superarlo. Sabemos que las circunstancias a veces no
            ayudan, así que queremos ofrecerte la posibilidad de
            <strong>reiniciar el curso desde cero</strong>.
        </p>

        <div style="background: #FEF2F2; border: 2px solid #7f1d1d; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #7f1d1d;">Tu situación final</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #7f1d1d; width: 200px;">Curso finalizado el:</td>
                    <td style="padding: 6px 0; font-size: 16px;">{{ $grupo->fecha_fin?->format('d/m/Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #7f1d1d;">Tu puntuación final:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        @if($noApto->nota_total !== null && $noApto->nota_max !== null)
                            <strong>{{ rtrim(rtrim(number_format((float)$noApto->nota_total, 2, ',', ''), '0'), ',') }}</strong>
                            <span style="color: #6B7280;"> de {{ rtrim(rtrim(number_format((float)$noApto->nota_max, 2, ',', ''), '0'), ',') }} puntos (no alcanzaste los 50)</span>
                        @else
                            <span style="color: #6B7280;">—</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <p>
            Si quieres aprovechar esta nueva oportunidad, solo tienes que pulsar el botón
            de abajo. Se abrirá tu cliente de email con un mensaje predefinido al equipo
            administrativo de WebCurso. Ellos te contactarán para coordinar las nuevas
            fechas y la matriculación.
        </p>

        <p style="text-align: center; margin: 28px 0;">
            <a href="{{ $mailtoUrl }}" style="display: inline-block; background: #059669; color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Sí, quiero reiniciar el curso
            </a>
        </p>

        <p style="font-size: 13px; color: #6B7280; text-align: center;">
            (Si el botón no funciona, responde a este correo escribiendo "Quiero reiniciar"
            o escríbenos directamente a
            <a href="mailto:{{ $adminEmail }}" style="color: #4F46E5;">{{ $adminEmail }}</a>.)
        </p>

        <p>
            Si prefieres no continuar con el curso, simplemente ignora este correo y no
            tomaremos ninguna acción. Si no respondes en los próximos 30 días, dejaremos
            de enviarte recordatorios.
        </p>

        <p style="font-size: 12px; color: #9ca3af; margin-top: 24px;">
            Aviso {{ $numOfrecimiento }} de {{ $maxOfrecimientos }}.
        </p>

        <p>
            Un saludo,<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
