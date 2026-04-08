<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Credenciales de acceso</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #4F46E5; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">WebCurso</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.9;">Credenciales de acceso a tu curso</p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Estimado/a <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>Te enviamos los datos de acceso para tu curso de formacion:</p>

        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #6B7280; width: 140px;">Curso:</td>
                    <td style="padding: 8px 0;">{{ $cursoNombre }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #6B7280;">Horas:</td>
                    <td style="padding: 8px 0;">{{ $cursoHoras }}h</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #6B7280;">Fecha inicio:</td>
                    <td style="padding: 8px 0;">{{ $fechaInicio?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; color: #6B7280;">Fecha fin:</td>
                    <td style="padding: 8px 0;">{{ $fechaFin?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div style="background: #EEF2FF; border: 2px solid #4F46E5; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #4F46E5;">Datos de acceso</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4F46E5;">Acceso al curso:</td>
                    <td style="padding: 6px 0;"><a href="{{ $courseUrl }}" style="color: #4F46E5;">{{ $courseUrl }}</a></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4F46E5;">Usuario:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $username }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4F46E5;">Contraseña:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $password }}</strong></td>
                </tr>
            </table>
        </div>

        @if($esBonificado)
        <p>Te recordamos que tu desempeño en este curso será determinante para la bonificación del mismo. Así que te animamos a participar activamente, cumplir con las fechas límite y aprovechar al máximo los recursos disponibles en el aula virtual.</p>
        @else
        <p>Te animamos a participar activamente y aprovechar al máximo los recursos disponibles en el aula virtual.</p>
        @endif

        <p>Si tienes alguna pregunta o necesitas ayuda con el acceso o el uso de la plataforma, no dudes en ponerte en contacto con nuestro equipo de soporte técnico a través de <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>.</p>

        <p>Estamos aquí para ayudarte en todo momento, así que no dudes en responder a este correo si tienes alguna duda o necesitas asistencia adicional.</p>

        <p><strong>¡Te deseamos mucho éxito en este curso!</strong></p>

        <p style="margin-top: 24px;">
            Un saludo,<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
