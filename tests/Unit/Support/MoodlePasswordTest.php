<?php

use App\Support\MoodlePassword;

test('elimina la tilde del primer nombre', function () {
    expect(MoodlePassword::generar('José'))->toBe('Jose4444*');
});

test('toma solo el primer nombre cuando hay nombre compuesto', function () {
    expect(MoodlePassword::generar('José Antonio Pérez Jiménez'))->toBe('Jose4444*');
});

test('convierte ñ a n', function () {
    expect(MoodlePassword::generar('Núñez'))->toBe('Nunez4444*');
});

test('convierte ü a u', function () {
    expect(MoodlePassword::generar('Raül'))->toBe('Raul4444*');
});

test('elimina tilde en María', function () {
    expect(MoodlePassword::generar('María'))->toBe('Maria4444*');
});

test('aplica lower+ucfirst sobre nombre en mayúsculas', function () {
    expect(MoodlePassword::generar('ANA'))->toBe('Ana4444*');
});

test('aplica ucfirst sobre nombre en minúsculas', function () {
    expect(MoodlePassword::generar('ana'))->toBe('Ana4444*');
});

test('trim de espacios alrededor del nombre', function () {
    expect(MoodlePassword::generar('  José  Antonio  '))->toBe('Jose4444*');
});

test('nombre vacío genera solo el sufijo', function () {
    expect(MoodlePassword::generar(''))->toBe('4444*');
});

test('nombre null genera solo el sufijo', function () {
    expect(MoodlePassword::generar(null))->toBe('4444*');
});

test('nombre sin acentos se mantiene intacto', function () {
    expect(MoodlePassword::generar('Carlos'))->toBe('Carlos4444*');
});
