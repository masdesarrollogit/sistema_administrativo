<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Candidatos Pendientes</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4F46E5; color: white; padding: 15px; text-align: center; border-radius: 8px 8px 0 0; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; }
        .table th { background-color: #f3f4f6; font-weight: bold; }
        .requisitos { font-size: 12px; color: #6b7280; }
        .observacion { color: #dc2626; font-style: italic; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 12px; text-align: center; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Candidatos Pendientes de Confirmación</h1>
        </div>
        
        <p>Hola,</p>
        <p>A continuación se detalla la lista de candidatos que actualmente tienen el estatus <strong>Pendiente</strong>:</p>

        <table class="table">
            <thead>
                <tr>
                    <th>Candidato / Entidad</th>
                    <th>Observación</th>
                    <th>Requisitos Pendientes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($candidatos as $candidato)
                <tr>
                    <td>
                        <strong>{{ $candidato->nombre_contacto }}</strong><br>
                        <small>{{ $candidato->email }}</small><br>
                        <span style="font-size: 11px; color: #4F46E5;">{{ $candidato->nombre_entidad }}</span>
                    </td>
                    <td class="observacion">
                        {{ $candidato->observacion ?: 'Sin observaciones' }}
                    </td>
                    <td class="requisitos">
                        @php
                            $faltantes = $candidato->requisitosFaltantes();
                        @endphp
                        @if($faltantes->count() > 0)
                            <ul style="margin: 0; padding-left: 15px;">
                                @foreach($faltantes as $req)
                                    <li>{{ $req->tipoRequisito->nombre }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span style="color: #059669;">Todos enviados</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <p>Este informe se genera automáticamente a las 13:00 (Hora España).</p>
            <p>© {{ date('Y') }} Webcurso</p>
        </div>
    </div>
</body>
</html>
