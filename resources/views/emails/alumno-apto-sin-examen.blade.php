<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Ya casi apruebas — solo te falta el cuestionario final</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #ca8a04; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">🎯 WebCurso</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.95;">
            Ya tienes los 50 puntos — solo te falta el cuestionario final
        </p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            ¡Gran trabajo en <strong>{{ $cursoNombre }}</strong>! Ya has superado los <strong>50 puntos</strong>
            necesarios entre tests y actividades. Estás muy cerca de aprobar el curso.
        </p>

        <div style="background: #FEF9C3; border: 2px solid #ca8a04; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #854d0e;">Tu progreso</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #854d0e; width: 200px;">Puntuación acumulada:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        @if($notaTotal !== null && $notaMax !== null)
                            <strong style="color: #854d0e;">{{ rtrim(rtrim(number_format((float)$notaTotal, 2, ',', ''), '0'), ',') }}</strong>
                            <span style="color: #6B7280;"> de {{ rtrim(rtrim(number_format((float)$notaMax, 2, ',', ''), '0'), ',') }} puntos</span>
                            @if($notaPorcentaje !== null)
                                <span style="color: #854d0e;"> ({{ number_format($notaPorcentaje, 1) }}%)</span>
                            @endif
                            <span style="color: #047857; font-weight: bold;"> ✓ Umbral superado</span>
                        @else
                            <span style="color: #6B7280;">—</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #854d0e;">Días para finalizar:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $diasRestantes }} días</strong></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #854d0e;">Cuestionario final:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        <span style="color: #b91c1c; font-weight: bold;">Pendiente</span>
                    </td>
                </tr>
            </table>
        </div>

        <p style="background: #d1fae5; border-left: 4px solid #059669; padding: 12px; margin: 12px 0; border-radius: 4px;">
            <strong>✓ El cuestionario final ya está disponible en tu aula.</strong> Es el último paso
            para que el curso quede oficialmente aprobado y se pueda bonificar ante FUNDAE.
        </p>

        <p>
            Te animamos a realizarlo lo antes posible — has trabajado duro para llegar hasta aquí
            y no queremos que pierdas la bonificación por dejarlo para el final.
        </p>

        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $courseUrl }}" style="display: inline-block; background: #ca8a04; color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Hacer el cuestionario final
            </a>
        </p>

        <p>
            Si tienes alguna duda con el cuestionario o problemas técnicos, responde a este correo
            o escríbenos a <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>.
        </p>

        <p>
            ¡Un saludo y mucho ánimo en la recta final!<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
