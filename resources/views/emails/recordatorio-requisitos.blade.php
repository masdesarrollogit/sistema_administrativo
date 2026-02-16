<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Requisitos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .requisitos-list {
            background-color: white;
            border-left: 4px solid #4F46E5;
            padding: 15px;
            margin: 20px 0;
        }
        .requisito-item {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .requisito-item:last-child {
            border-bottom: none;
        }
        .requisito-nombre {
            font-weight: bold;
            color: #4F46E5;
        }
        .requisito-descripcion {
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }
        .footer {
            background-color: #f3f4f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-radius: 0 0 8px 8px;
        }
        .cta-button {
            display: inline-block;
            background-color: #4F46E5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .info-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Recordatorio de Requisitos Pendientes</h1>
    </div>

    <div class="content">
        <p>Hola <strong>{{ $candidato->nombre_contacto }}</strong>,</p>

        @if($candidato->descripcion_personalizada)
            <div style="background-color: #fff; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px; margin: 20px 0; font-style: italic;">
                {!! nl2br(e($candidato->descripcion_personalizada)) !!}
            </div>
        @elseif($candidato->observacion)
            <div style="background-color: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 8px; margin: 20px 0; color: #991b1b; font-weight: bold;">
                Nota importante: {!! nl2br(e($candidato->observacion)) !!}
            </div>
        @else
            <p>Te escribimos desde <strong>Webcurso</strong> para recordarte que aún tienes algunos requisitos pendientes para poder iniciar tu curso.</p>
        @endif

        @if($candidato->curso_nombre)
        <p>Curso seleccionado: <strong>{{ $candidato->curso_nombre }}</strong></p>
        @endif

        <div class="info-box">
            <strong>📋 Estado de los Requisitos:</strong>
        </div>

        <div class="requisitos-list">
            @foreach($candidato->requisitos->sortBy('tipoRequisito.orden') as $requisito)
            <div class="requisito-item">
                <div class="requisito-nombre" style="color: {{ $requisito->estado == 'completado' ? '#059669' : '#dc2626' }}">
                    {{ $requisito->estado == 'completado' ? '✅' : '❌' }} {{ $requisito->tipoRequisito->nombre }}
                </div>
                @if($requisito->tipoRequisito->descripcion)
                <div class="requisito-descripcion">
                    {{ $requisito->tipoRequisito->descripcion }}
                </div>
                @endif
            </div>
            @endforeach
        </div>

        <p>Para poder proceder con tu matriculación, necesitamos que completes estos requisitos lo antes posible.</p>

        <p>Si tienes alguna duda o necesitas ayuda, no dudes en contactarnos respondiendo a este correo.</p>

        <p>Saludos cordiales,<br>
        <strong>El equipo de Webcurso</strong></p>
    </div>

    <div class="footer">
        <p>Este es un mensaje automático. Por favor, no respondas directamente a este correo.</p>
        <p>© {{ date('Y') }} Webcurso. Todos los derechos reservados.</p>
    </div>
</body>
</html>
