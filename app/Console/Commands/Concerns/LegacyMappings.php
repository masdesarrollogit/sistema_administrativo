<?php

namespace App\Console\Commands\Concerns;

trait LegacyMappings
{
    private const NIVEL_ESTUDIOS = [
        'Menos que primaria' => 1,
        'Educación Primaria' => 2,
        'Primera Etapa de Educación secundaria' => 3,
        'Segunda Etapa de Educación Secundaria' => 4,
        'Educación post secundaria no superior' => 5,
        'Técnico Superior/FP grado superior y equivalentes' => 6,
        'E universitarios 1 er ciclo (Diplomatura-Grados)' => 7,
        'E universitarios 2 do ciclo (Licenciatura- Master)' => 8,
        'E universitarios 3 er ciclo (Doctorado)' => 9,
        'Otras Titulaciones' => 10,
    ];

    private const CATEGORIA_PROFESIONAL = [
        'Directivo' => 1,
        'Mando Intermedio' => 2,
        'Técnico' => 3,
        'Trabajador cualificado' => 4,
        'Trabajador con Baja cualificación' => 5,
    ];

    private const GRUPO_COTIZACION = [
        'Ingenieros y Licenciados' => '1',
        'Ingenieros Técnicos, peritos y ayudantes titulados' => '2',
        'Jefes administrativos y de taller' => '3',
        'Ayudantes no titulados' => '4',
        'Oficiales administrativos' => '5',
        'Subalternos' => '6',
        'Auxiliares administrativos' => '7',
        'Oficiales de primera y de segunda' => '8',
        'Oficiales de Tercera y especialistas' => '9',
        'Trabajadores mayores de 18 años no cualificados' => '10',
        'Trabajadores menos de 18 años' => '11',
    ];

    protected function mapearNivelEstudios(?string $texto): ?int
    {
        if (!$texto) {
            return null;
        }

        foreach (self::NIVEL_ESTUDIOS as $clave => $codigo) {
            if (mb_stripos($texto, mb_substr($clave, 0, 15)) !== false) {
                return $codigo;
            }
        }

        return null;
    }

    protected function mapearCategoriaProfesional(?string $texto): ?int
    {
        if (!$texto) {
            return null;
        }

        foreach (self::CATEGORIA_PROFESIONAL as $clave => $codigo) {
            if (mb_stripos($texto, mb_substr($clave, 0, 10)) !== false) {
                return $codigo;
            }
        }

        return null;
    }

    protected function mapearGrupoCotizacion(?string $texto): ?string
    {
        if (!$texto) {
            return null;
        }

        foreach (self::GRUPO_COTIZACION as $clave => $codigo) {
            if (mb_stripos($texto, mb_substr($clave, 0, 15)) !== false) {
                return $codigo;
            }
        }

        return null;
    }

    /**
     * Separar "Escalona Alarcon" en apellido1 y apellido2.
     * Limpia sufijos legacy tipo "(REPASO)".
     */
    protected function separarApellidos(?string $lastNameCompleto): array
    {
        $lastNameCompleto = (string) $lastNameCompleto;
        $limpio = preg_replace('/\s*\(.*?\)\s*$/', '', trim($lastNameCompleto));
        $parts = preg_split('/\s+/', trim($limpio), 2);

        return [
            'apellido1' => $parts[0] ?? '',
            'apellido2' => $parts[1] ?? '',
        ];
    }

    /**
     * Separar nombre completo de FUNDAE "JOSSELINE XIOMA TORRES GARCIA"
     * en nombre, apellido1, apellido2.
     */
    protected function separarNombreCompleto(?string $nombreCompleto): array
    {
        $parts = preg_split('/\s+/', trim((string) $nombreCompleto));

        if (count($parts) <= 1) {
            return ['nombre' => $parts[0] ?? '', 'apellido1' => '', 'apellido2' => ''];
        }

        if (count($parts) === 2) {
            return ['nombre' => $parts[0], 'apellido1' => $parts[1], 'apellido2' => ''];
        }

        $primerToken = $parts[0];
        if (mb_strlen($primerToken) <= 2 && count($parts) >= 4) {
            return [
                'nombre' => $parts[0] . ' ' . $parts[1],
                'apellido1' => $parts[2],
                'apellido2' => implode(' ', array_slice($parts, 3)),
            ];
        }

        return [
            'nombre' => $parts[0],
            'apellido1' => $parts[1],
            'apellido2' => implode(' ', array_slice($parts, 2)),
        ];
    }

    /**
     * Normaliza un CIF: TRIM + UPPER + sin espacios, guiones, puntos.
     * Devuelve null si el resultado vacío o no tiene formato CIF empresa válido.
     */
    protected function normalizarCif(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($valor)));

        if ($norm === '' || !preg_match('/^[A-HJNPQRSUVW][0-9]{8}$/', $norm)) {
            return null;
        }

        return $norm;
    }

    /**
     * Normaliza una razón social para fuzzy match: TRIM + UPPER + sin puntos/comas,
     * sustituye `S L` y `SL` por `SL`, `S A` por `SA`, etc.
     */
    protected function normalizarRazonSocial(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $norm = strtoupper(trim($valor));
        $norm = preg_replace('/[\.,;]+/', '', $norm);
        $norm = preg_replace('/\s+S\s*L\s*$/u', ' SL', $norm);
        $norm = preg_replace('/\s+S\s*A\s*$/u', ' SA', $norm);
        $norm = preg_replace('/\s+S\s*L\s*U\s*$/u', ' SLU', $norm);
        $norm = preg_replace('/\s+S\s*A\s*U\s*$/u', ' SAU', $norm);
        $norm = preg_replace('/\s+/', ' ', $norm);

        return trim($norm) ?: null;
    }

    /**
     * Resolución multi-fuente del CIF empresa para un registro de tbl_member.
     * Devuelve el CIF normalizado o null.
     *
     * Orden de búsqueda:
     *   1. nid si tiene formato CIF empresa
     *   2. company si tiene formato CIF empresa (registros antiguos)
     */
    protected function resolverCifLegacy(?string $nid, ?string $company): ?string
    {
        $cif = $this->normalizarCif($nid);
        if ($cif !== null) {
            return $cif;
        }

        $cif = $this->normalizarCif($company);
        if ($cif !== null) {
            return $cif;
        }

        return null;
    }

    /**
     * ¿El personal_id legacy tiene formato NIF persona válido?
     * Acepta NIF (8 dig + letra), NIE (X/Y/Z + 7 dig + letra) tras normalizar.
     */
    protected function esNifValido(?string $personalId): bool
    {
        if (!$personalId) {
            return false;
        }

        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($personalId)));

        if ($norm === '' || $norm === '0') {
            return false;
        }

        if (preg_match('/^[0-9]{8}[A-Z]$/', $norm)) {
            return true;
        }

        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $norm)) {
            return true;
        }

        return false;
    }

    protected function normalizarNif(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($valor)));
        return $norm ?: null;
    }
}
