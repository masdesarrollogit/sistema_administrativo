<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Webcurso\Dashboard;
use App\Livewire\Webcurso\EmpresasIndex;
use App\Livewire\Webcurso\GruposIndex;
use App\Livewire\Webcurso\EmpresasSinGrupos;
use App\Livewire\Webcurso\ImportarCsv;
use App\Livewire\Webcurso\ParticipantesBonificadosIndex;
use App\Livewire\Webcurso\CandidatosIndex;
use App\Livewire\Webcurso\CandidatoForm;
use App\Livewire\Webcurso\CandidatoEstatus;
use App\Livewire\Webcurso\TutoresIndex;
use App\Livewire\Webcurso\AccionesFormativasIndex;
use App\Livewire\Webcurso\AlumnosIndex;
use App\Livewire\Webcurso\ReportesMoodleIndex;


/*
|--------------------------------------------------------------------------
| WebCurso Panel Routes
|--------------------------------------------------------------------------
|
| Rutas para el panel de gestión de empresas y grupos de formación FUNDAE.
| Todas las rutas están protegidas por autenticación y requieren rol admin.
|
*/

Route::middleware(['auth', 'role:admin|SuperAdmin'])
    ->prefix('webcurso')
    ->name('webcurso.')
    ->group(function () {
        
        // Dashboard principal
        Route::get('/', Dashboard::class)->name('dashboard');
        
        // Gestión de empresas
        Route::get('/empresas', EmpresasIndex::class)->name('empresas');
        
        // Gestión de grupos
        Route::get('/grupos', GruposIndex::class)->name('grupos');
        
        // Empresas sin grupos
        Route::get('/empresas-sin-grupos', EmpresasSinGrupos::class)->name('empresas-sin-grupos');
        
        // Importar archivos
        Route::get('/importar', ImportarCsv::class)->name('importar');

        // Participantes Bonificados FUNDAE
        Route::get('/participantes-bonificados', ParticipantesBonificadosIndex::class)->name('participantes-bonificados');
        
        // Tutores
        Route::get('/tutores', TutoresIndex::class)->name('tutores');

        // Alumnos
        Route::get('/alumnos', AlumnosIndex::class)->name('alumnos');

        // Reportes Moodle (Fase 1: alumnos no conectados)
        Route::get('/reportes-moodle', ReportesMoodleIndex::class)->name('reportes-moodle');

        // Acciones Formativas FUNDAE
        Route::get('/acciones-formativas', AccionesFormativasIndex::class)->name('acciones-formativas');

        // Gestión de candidatos
        Route::prefix('candidatos')->name('candidatos.')->group(function () {
            Route::get('/', CandidatosIndex::class)->name('index');
            Route::get('/crear', CandidatoForm::class)->name('crear');
            Route::get('/{candidato}/editar', CandidatoForm::class)->name('editar');
            Route::get('/{candidato}/estatus', CandidatoEstatus::class)->name('estatus');
        });
    });
