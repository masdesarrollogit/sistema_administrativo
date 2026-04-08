<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Saldo disponible para formación bonificada</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333;">
    <div style="width: 600px; margin: 0 auto;">
        <table cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;">
            <tbody>
                <tr>
                    <td style="text-align: center;">
                        <img src="{{ $message->embed(public_path('images/logo-email.jpg')) }}" alt="WebCurso" style="max-width: 100%; height: auto; display: block;">
                    </td>
                </tr>
                <tr>
                    <td style="padding: 30px;">
                        <p>Estimado/a <strong>{{ $nombreParticipante }}</strong>,</p>

                        <p>Nos ponemos en contacto contigo para informarte de que la empresa <strong>{{ $razonSocial }}</strong> dispone de saldo en créditos de formación bonificada por FUNDAE para este año {{ date('Y') }}.</p>

                        <div style="background: #e7f3ff; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0;">
                            <p style="margin: 0; font-size: 16px;">Saldo disponible:</p>
                            <p style="margin: 10px 0 0 0; font-size: 32px; font-weight: bold; color: #007cba;">
                                {{ $saldoFormateado }}
                            </p>
                            <p style="margin: 5px 0 0 0; color: #666;">
                                en créditos para formación bonificada — año {{ date('Y') }}
                            </p>
                        </div>

                        <p><strong>Datos de la empresa:</strong></p>
                        <ul>
                            <li><strong>CIF:</strong> {{ $cif }}</li>
                            <li><strong>Razón Social:</strong> {{ $razonSocial }}</li>
                        </ul>

                        <p>Te invito a explorar nuestra oferta formativa actualizada a través del siguiente enlace:</p>

                        <div style="text-align: center; margin: 25px 0;">
                            <a href="https://www.webcurso.es/courses"
                               style="display: inline-block; padding: 14px 30px; background: #007cba; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px;">
                                🌐 Ver cursos disponibles
                            </a>
                        </div>

                        <p>Si algún curso despierta tu interés o necesitas información detallada sobre los contenidos y la bonificación, estaré encantado de asesorarte para encontrar la mejor opción para tu equipo.</p>

                        <p>Puedes responder directamente a este correo y te atenderemos a la mayor brevedad.</p>

                        <p>Un saludo cordial,</p>

                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                            <p style="margin: 0; color: #666; font-size: 12px;">
                                <strong>Departamento de Administración</strong><br>
                                {{ config('candidatos.contacto.telefono') }}<br>
                                <a href="mailto:administracion@webcurso.es" style="color: #007cba; text-decoration: none;">administracion@webcurso.es</a>
                            </p>
                            @if(config('candidatos.contacto.whatsapp'))
                            <p style="margin: 15px 0 0 0; font-size: 11px; color: #999;">
                                ¡Escríbenos al Whatsapp!
                                <a href="https://api.whatsapp.com/send?phone={{ config('candidatos.contacto.whatsapp') }}" style="color: #25D366;">{{ config('candidatos.contacto.whatsapp_display') }}</a>
                            </p>
                            @endif
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
