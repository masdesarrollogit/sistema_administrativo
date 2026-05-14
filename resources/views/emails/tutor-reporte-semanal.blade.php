<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Reporte semanal de alumnos</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 760px; margin: 0 auto; padding: 20px;">

    <div style="background: #4F46E5; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">WebCurso · Reporte semanal</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.9;">Semana del {{ $semanaEtiqueta }}</p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $tutor->nombre }}</strong>,</p>

        <p>
            Te enviamos el resumen de tus alumnos que requieren atención esta semana.
            Una llamada o un mensaje suele ser el empujón que necesitan.
        </p>

        @if(count($noConectados) > 0)
            <h3 style="color: #475569; border-bottom: 2px solid #94a3b8; padding-bottom: 4px; margin-top: 24px;">
                🔘 No han entrado al curso ({{ count($noConectados) }})
            </h3>
            <table style="width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 13px;">
                <thead>
                    <tr style="background: #475569; color: white;">
                        <th style="padding: 8px; text-align: left;">Alumno</th>
                        <th style="padding: 8px; text-align: left;">Teléfono</th>
                        <th style="padding: 8px; text-align: left;">Curso</th>
                        <th style="padding: 8px; text-align: left;">Grupo</th>
                        <th style="padding: 8px; text-align: right;">Días desde inicio</th>
                        <th style="padding: 8px; text-align: right;">Emails al alumno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($noConectados as $a)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">{{ $a['alumno_nombre'] }}</td>
                            <td style="padding: 8px;">
                                @if(!empty($a['alumno_telefono']))
                                    <a href="tel:{{ $a['alumno_telefono'] }}" style="color: #4F46E5;">{{ $a['alumno_telefono'] }}</a>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px;">{{ $a['curso'] }}</td>
                            <td style="padding: 8px;">{{ $a['grupo'] }}</td>
                            <td style="padding: 8px; text-align: right;">{{ $a['dias_desde_inicio'] }}</td>
                            <td style="padding: 8px; text-align: right;">{{ $a['emails_enviados'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($preCierre) > 0)
            <h3 style="color: #b45309; border-bottom: 2px solid #f59e0b; padding-bottom: 4px; margin-top: 24px;">
                🚨 Pre-cierre — últimas 72h ({{ count($preCierre) }})
            </h3>
            <p style="font-size: 12px; color: #6B7280; margin: 4px 0 8px;">
                Alumnos a punto de cerrar el curso SIN cuestionario final realizado. Urgencia máxima.
            </p>
            <table style="width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 13px;">
                <thead>
                    <tr style="background: #b45309; color: white;">
                        <th style="padding: 8px; text-align: left;">Alumno</th>
                        <th style="padding: 8px; text-align: left;">Teléfono</th>
                        <th style="padding: 8px; text-align: left;">Curso</th>
                        <th style="padding: 8px; text-align: left;">Grupo</th>
                        <th style="padding: 8px; text-align: right;">Horas restantes</th>
                        <th style="padding: 8px; text-align: right;">Nota</th>
                        <th style="padding: 8px; text-align: center;">Cuestionario</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($preCierre as $a)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">{{ $a['alumno_nombre'] }}</td>
                            <td style="padding: 8px;">
                                @if(!empty($a['alumno_telefono']))
                                    <a href="tel:{{ $a['alumno_telefono'] }}" style="color: #4F46E5;">{{ $a['alumno_telefono'] }}</a>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px;">{{ $a['curso'] }}</td>
                            <td style="padding: 8px;">{{ $a['grupo'] }}</td>
                            <td style="padding: 8px; text-align: right;"><strong style="color: #b45309;">{{ $a['horas_restantes'] }}h</strong></td>
                            <td style="padding: 8px; text-align: right;">
                                @if(isset($a['nota_pct']) && $a['nota_pct'] !== null)
                                    {{ number_format($a['nota_pct'], 1) }}%
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                <span style="color: #b91c1c;">⚠ Pendiente</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($riesgoCritico) > 0)
            <h3 style="color: #b91c1c; border-bottom: 2px solid #f87171; padding-bottom: 4px; margin-top: 24px;">
                🔴 Riesgo crítico ({{ count($riesgoCritico) }})
            </h3>
            <p style="font-size: 12px; color: #6B7280; margin: 4px 0 8px;">
                Alumnos con &lt;50 pts y ya transcurrió la mitad del tiempo del curso. Necesitan acción urgente.
            </p>
            <table style="width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 13px;">
                <thead>
                    <tr style="background: #b91c1c; color: white;">
                        <th style="padding: 8px; text-align: left;">Alumno</th>
                        <th style="padding: 8px; text-align: left;">Teléfono</th>
                        <th style="padding: 8px; text-align: left;">Curso</th>
                        <th style="padding: 8px; text-align: left;">Grupo</th>
                        <th style="padding: 8px; text-align: right;">Días restantes</th>
                        <th style="padding: 8px; text-align: right;">Tiempo trans.</th>
                        <th style="padding: 8px; text-align: right;">Nota</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($riesgoCritico as $a)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">{{ $a['alumno_nombre'] }}</td>
                            <td style="padding: 8px;">
                                @if(!empty($a['alumno_telefono']))
                                    <a href="tel:{{ $a['alumno_telefono'] }}" style="color: #4F46E5;">{{ $a['alumno_telefono'] }}</a>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px;">{{ $a['curso'] }}</td>
                            <td style="padding: 8px;">{{ $a['grupo'] }}</td>
                            <td style="padding: 8px; text-align: right;">{{ $a['dias_restantes'] }}</td>
                            <td style="padding: 8px; text-align: right;">
                                @if(isset($a['pct_tiempo_transcurrido']) && $a['pct_tiempo_transcurrido'] !== null)
                                    {{ number_format($a['pct_tiempo_transcurrido'], 0) }}%
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                @if(isset($a['nota_pct']) && $a['nota_pct'] !== null)
                                    <strong style="color: #b91c1c;">{{ number_format($a['nota_pct'], 1) }}%</strong>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($inactivos) > 0)
            <h3 style="color: #4F46E5; border-bottom: 2px solid #818cf8; padding-bottom: 4px; margin-top: 24px;">
                🟣 Inactivos > 3 días ({{ count($inactivos) }})
            </h3>
            <table style="width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 13px;">
                <thead>
                    <tr style="background: #4F46E5; color: white;">
                        <th style="padding: 8px; text-align: left;">Alumno</th>
                        <th style="padding: 8px; text-align: left;">Teléfono</th>
                        <th style="padding: 8px; text-align: left;">Curso</th>
                        <th style="padding: 8px; text-align: left;">Grupo</th>
                        <th style="padding: 8px; text-align: right;">Días inactivo</th>
                        <th style="padding: 8px; text-align: right;">Días restantes</th>
                        <th style="padding: 8px; text-align: right;">Nota</th>
                        <th style="padding: 8px; text-align: center;">Aprobado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inactivos as $a)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">{{ $a['alumno_nombre'] }}</td>
                            <td style="padding: 8px;">
                                @if(!empty($a['alumno_telefono']))
                                    <a href="tel:{{ $a['alumno_telefono'] }}" style="color: #4F46E5;">{{ $a['alumno_telefono'] }}</a>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px;">{{ $a['curso'] }}</td>
                            <td style="padding: 8px;">{{ $a['grupo'] }}</td>
                            <td style="padding: 8px; text-align: right;">{{ $a['dias_inactivo'] }}</td>
                            <td style="padding: 8px; text-align: right;">{{ $a['dias_restantes'] }}</td>
                            <td style="padding: 8px; text-align: right;">
                                @if(isset($a['nota_pct']) && $a['nota_pct'] !== null)
                                    {{ number_format($a['nota_pct'], 1) }}%
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                @if(!empty($a['aprobado']))
                                    <span style="color: #047857; font-weight: bold;">SÍ</span>
                                @else
                                    <span style="color: #b91c1c;">No</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($aptoSinExamen) > 0)
            <h3 style="color: #854d0e; border-bottom: 2px solid #facc15; padding-bottom: 4px; margin-top: 24px;">
                🟡 Apto sin examen — empuje positivo ({{ count($aptoSinExamen) }})
            </h3>
            <p style="font-size: 12px; color: #6B7280; margin: 4px 0 8px;">
                Alumnos que YA tienen 50+ pts pero no han realizado el cuestionario final. Solo necesitan un empujón.
            </p>
            <table style="width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 13px;">
                <thead>
                    <tr style="background: #ca8a04; color: white;">
                        <th style="padding: 8px; text-align: left;">Alumno</th>
                        <th style="padding: 8px; text-align: left;">Teléfono</th>
                        <th style="padding: 8px; text-align: left;">Curso</th>
                        <th style="padding: 8px; text-align: left;">Grupo</th>
                        <th style="padding: 8px; text-align: right;">Días restantes</th>
                        <th style="padding: 8px; text-align: right;">Nota</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aptoSinExamen as $a)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">{{ $a['alumno_nombre'] }}</td>
                            <td style="padding: 8px;">
                                @if(!empty($a['alumno_telefono']))
                                    <a href="tel:{{ $a['alumno_telefono'] }}" style="color: #4F46E5;">{{ $a['alumno_telefono'] }}</a>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                            <td style="padding: 8px;">{{ $a['curso'] }}</td>
                            <td style="padding: 8px;">{{ $a['grupo'] }}</td>
                            <td style="padding: 8px; text-align: right;">{{ $a['dias_restantes'] }}</td>
                            <td style="padding: 8px; text-align: right;">
                                @if(isset($a['nota_pct']) && $a['nota_pct'] !== null)
                                    <strong style="color: #047857;">{{ number_format($a['nota_pct'], 1) }}%</strong>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($aprobados) > 0)
            <h3 style="color: #065f46; border-bottom: 2px solid #34d399; padding-bottom: 4px; margin-top: 24px;">
                ✅ Aprobados — éxitos ({{ count($aprobados) }})
            </h3>
            <p style="font-size: 12px; color: #6B7280; margin: 4px 0 8px;">
                Alumnos que ya completaron y aprobaron el curso. ¡Buen trabajo del equipo!
            </p>
            <table style="width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 13px;">
                <thead>
                    <tr style="background: #059669; color: white;">
                        <th style="padding: 8px; text-align: left;">Alumno</th>
                        <th style="padding: 8px; text-align: left;">Curso</th>
                        <th style="padding: 8px; text-align: left;">Grupo</th>
                        <th style="padding: 8px; text-align: right;">Nota final</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aprobados as $a)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">{{ $a['alumno_nombre'] }}</td>
                            <td style="padding: 8px;">{{ $a['curso'] }}</td>
                            <td style="padding: 8px;">{{ $a['grupo'] }}</td>
                            <td style="padding: 8px; text-align: right;">
                                @if(isset($a['nota_pct']) && $a['nota_pct'] !== null)
                                    <strong style="color: #047857;">{{ number_format($a['nota_pct'], 1) }}%</strong>
                                @else
                                    <span style="color: #9CA3AF;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($noConectados) === 0 && count($inactivos) === 0 && count($riesgoCritico) === 0 && count($preCierre) === 0 && count($aptoSinExamen) === 0 && count($aprobados) === 0)
            <p style="background: #ecfdf5; border: 1px solid #10b981; padding: 12px; border-radius: 6px; color: #065f46;">
                Buenas noticias: ningún alumno tuyo requiere atención esta semana 🎉
            </p>
        @endif

        <p style="margin-top: 24px;">
            Cualquier comentario o gestión que hagas, escríbenos a
            <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>.
        </p>

        <p>
            Un saludo,<br>
            <strong>Equipo WebCurso</strong>
        </p>
    </div>
</body>
</html>
