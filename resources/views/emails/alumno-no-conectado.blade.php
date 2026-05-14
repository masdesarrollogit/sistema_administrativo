<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>¿Tienes problemas para entrar a tu curso?</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #475569; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">WebCurso</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.9;">No detectamos tu acceso al curso</p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            Han pasado <strong>{{ $diasDesdeInicio }} días</strong> desde el inicio de tu curso
            <strong>{{ $cursoNombre }}</strong> y aún no hemos detectado que hayas accedido al aula virtual.
        </p>

        <p>
            Para que la formación pueda bonificarse correctamente es imprescindible que entres y completes
            las actividades. Te enviamos de nuevo tus datos de acceso por si te resultan útiles:
        </p>

        <div style="background: #EEF2FF; border: 2px solid #475569; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #475569;">Datos de acceso</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #475569;">Acceso al curso:</td>
                    <td style="padding: 6px 0;"><a href="{{ $courseUrl }}" style="color: #4F46E5;">{{ $courseUrl }}</a></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #475569;">Usuario:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $username }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #475569;">Contraseña:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $password }}</strong></td>
                </tr>
            </table>
        </div>

        <p>
            Si has tenido algún problema técnico o no recuerdas haber recibido las credenciales, responde a este correo
            o escríbenos a <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>
            y te ayudaremos enseguida.
        </p>

        <p style="font-size: 13px; color: #6B7280; margin-top: 24px;">
            (Aviso {{ $intentoNumero }} de {{ config('reportes_moodle.reporte_no_conectados.tope_reenvios_alumno', 3) }}.)
        </p>

        <p>
            Un saludo,<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
