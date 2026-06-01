<?php

namespace App\Support;

use Illuminate\Support\Str;

class MoodlePassword
{
    public static function generar(?string $nombre): string
    {
        $primero = explode(' ', trim((string) $nombre))[0] ?? '';
        $ascii   = Str::ascii($primero);

        return Str::ucfirst(Str::lower($ascii)) . '4444*';
    }
}
