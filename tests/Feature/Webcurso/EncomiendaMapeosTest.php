<?php

use App\Console\Commands\Concerns\LegacyMappings;

/** Harness que expone los métodos protegidos del trait. */
function encomiendaMapper(): object
{
    return new class {
        use LegacyMappings {
            mapearNivelEstudiosEncomiendaLetra as public pubNivel;
            mapearCategoriaProfesionalEncomiendaRomano as public pubCat;
            mapearGrupoCotizacionEncomienda as public pubCot;
            normalizarFechaEncomienda as public pubFecha;
        }
    };
}

it('mapea nivel de estudios por letra A-J → 1-10', function () {
    $m = encomiendaMapper();
    expect($m->pubNivel('A'))->toBe(1);
    expect($m->pubNivel('D'))->toBe(4);
    expect($m->pubNivel('F'))->toBe(6);
    expect($m->pubNivel('H'))->toBe(8);
    expect($m->pubNivel('J'))->toBe(10);
    expect($m->pubNivel('a'))->toBe(1);      // case-insensitive
    expect($m->pubNivel('K'))->toBeNull();   // fuera de rango
    expect($m->pubNivel(''))->toBeNull();
    expect($m->pubNivel(null))->toBeNull();
});

it('mapea grupo profesional romano I-V → 1-5', function () {
    $m = encomiendaMapper();
    expect($m->pubCat('I'))->toBe(1);
    expect($m->pubCat('II'))->toBe(2);
    expect($m->pubCat('III'))->toBe(3);
    expect($m->pubCat('IV'))->toBe(4);
    expect($m->pubCat('V'))->toBe(5);
    expect($m->pubCat('VI'))->toBeNull();
    expect($m->pubCat(''))->toBeNull();
});

it('mapea grupo cotización quitando el cero, validado 1-11', function () {
    $m = encomiendaMapper();
    expect($m->pubCot('01'))->toBe('1');
    expect($m->pubCot('07'))->toBe('7');
    expect($m->pubCot('11'))->toBe('11');
    expect($m->pubCot('12'))->toBeNull();
    expect($m->pubCot(''))->toBeNull();
    expect($m->pubCot(null))->toBeNull();
});

it('parsea fechas del sistema externo en formatos sucios', function () {
    $m = encomiendaMapper();
    expect($m->pubFecha('21/01/1981'))->toBe('1981-01-21');
    expect($m->pubFecha('09/11/1983'))->toBe('1983-11-09');
    expect($m->pubFecha('02-10-2007'))->toBe('2007-10-02');
    expect($m->pubFecha('21011981'))->toBe('1981-01-21');   // 8 dígitos sin separador
    expect($m->pubFecha('2101981'))->toBeNull();            // 7 dígitos → ambiguo
    expect($m->pubFecha('32/13/2000'))->toBeNull();         // fecha inválida
    expect($m->pubFecha(''))->toBeNull();
    expect($m->pubFecha(null))->toBeNull();
});
