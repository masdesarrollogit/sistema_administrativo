<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Saldo de empresa</title>
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
                        <p>Hola: <strong>{{ $razon }}</strong>,</p>
                        
                        <p><b><u>Adjunto encontrarás el reporte actualizado de FUNDAE</u>.</b></p>
                        
                        <div style="background: #e7f3ff; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0;">
                            <p style="margin: 0; font-size: 16px;">Actualmente disponen de:</p>
                            <p style="margin: 10px 0 0 0; font-size: 28px; font-weight: bold; color: #007cba;">
                                {{ $saldoFormateado }}
                            </p>
                            <p style="margin: 5px 0 0 0; color: #666;">
                                en créditos para realizar formación bonificada durante este año {{ date('Y') }}.
                            </p>
                        </div>
                        
                        <p><strong>Datos de la empresa:</strong></p>
                        <ul>
                            <li><strong>CIF:</strong> {{ $cif }}</li>
                            <li><strong>Razón Social:</strong> {{ $razon }}</li>
                        </ul>

                        <p>El curso <strong>"" ( horas)</strong>  tiene un coste bonificado de <strong>€</strong> lo que significa que puede ser bonificado al 100%.</p>
                        
                        <p><b>Fechas propuestas:</b></p>
                        <ul>
                            <li>Fecha de inicio: Por confirmar</li>
                            <li>Fecha de finalización: Por confirmar</li>
                        </ul>
                        
                        <p>Quedamos atentos a sus comentarios para confirmar las fechas de inicio o resolver cualquier duda que puedan tener.</p>
                        
                        <p>Un saludo cariñoso,</p>
                        
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                            <p style="margin: 0; color: #666; font-size: 12px;">
                                <strong>Recibe un cordial saludo.</strong><br>
                                <strong>Departamento de Administración</strong><br>
                                {{ config('candidatos.contacto.telefono') }}<br>
                                <a href="mailto:{{ config('candidatos.contacto.email') }}" style="color: #007cba; text-decoration: none;">{{ config('candidatos.contacto.email') }}</a>
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
