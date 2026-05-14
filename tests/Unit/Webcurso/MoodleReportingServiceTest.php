<?php

use App\Services\Webcurso\MoodleReportingService;
use Modules\Moodle\Services\MoodleService;

it('getLastAccessGlobalBatch chunks user ids and merges results', function () {
    $moodle = Mockery::mock(MoodleService::class);

    $moodle->shouldReceive('getUsersByIds')
        ->once()
        ->with([1, 2])
        ->andReturn([
            1 => ['id' => 1, 'lastaccess' => 1000],
            2 => ['id' => 2, 'lastaccess' => 0],
        ]);

    $moodle->shouldReceive('getUsersByIds')
        ->once()
        ->with([3])
        ->andReturn([
            3 => ['id' => 3, 'lastaccess' => 2000],
        ]);

    $service = new MoodleReportingService($moodle);
    $result = $service->getLastAccessGlobalBatch([1, 2, 3], loteSize: 2);

    expect($result)->toBe([
        1 => 1000,
        2 => 0,
        3 => 2000,
    ]);
});

it('getLastAccessGlobalBatch returns 0 when user is not found in response', function () {
    $moodle = Mockery::mock(MoodleService::class);
    $moodle->shouldReceive('getUsersByIds')
        ->once()
        ->andReturn([]); // Moodle no devolvió ningún user

    $service = new MoodleReportingService($moodle);
    $result = $service->getLastAccessGlobalBatch([42]);

    expect($result)->toBe([42 => 0]);
});

it('getUserCourseStats picks the matching course and parses fields', function () {
    $moodle = Mockery::mock(MoodleService::class);
    $moodle->shouldReceive('getUserCourses')
        ->once()
        ->with(123)
        ->andReturn([
            ['id' => 100, 'lastaccess' => 1111, 'progress' => null, 'completed' => null, 'enablecompletion' => false, 'category' => 11],
            ['id' => 218, 'lastaccess' => 0,    'progress' => 12.5, 'completed' => false,  'enablecompletion' => true, 'category' => 1],
        ]);

    $service = new MoodleReportingService($moodle);
    $stats = $service->getUserCourseStats(123, 218);

    expect($stats)->toBe([
        'lastaccess'       => 0,
        'progress'         => 12.5,
        'completed'        => false,
        'enablecompletion' => true,
        'category'         => 1,
    ]);
});

it('getUserCourseStats returns null when user is not enrolled in that course', function () {
    $moodle = Mockery::mock(MoodleService::class);
    $moodle->shouldReceive('getUserCourses')->once()->andReturn([
        ['id' => 999, 'lastaccess' => 1, 'progress' => 0, 'completed' => false, 'enablecompletion' => true],
    ]);

    $service = new MoodleReportingService($moodle);
    $stats = $service->getUserCourseStats(123, 218);

    expect($stats)->toBeNull();
});

it('getLastAccessGlobalBatch deduplicates and skips invalid ids', function () {
    $moodle = Mockery::mock(MoodleService::class);
    // Solo debería pedir IDs únicos válidos: [5, 7]
    $moodle->shouldReceive('getUsersByIds')
        ->once()
        ->with([5, 7])
        ->andReturn([
            5 => ['id' => 5, 'lastaccess' => 100],
            7 => ['id' => 7, 'lastaccess' => 200],
        ]);

    $service = new MoodleReportingService($moodle);
    $result = $service->getLastAccessGlobalBatch([5, 5, 7, 0, null]);

    expect($result)->toBe([5 => 100, 7 => 200]);
});
