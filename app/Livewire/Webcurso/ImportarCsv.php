<?php

namespace App\Livewire\Webcurso;

use App\Services\Webcurso\CsvImportService;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportarCsv extends Component
{
    use WithFileUploads;

    public $archivoEmpresas;
    public $archivoGrupos;
    public $archivoParticipantes;
    public $archivoAccionesFormativas;
    public $archivoEncuesta;
    public bool $esAnterior = false;
    public array $logs = [];
    public bool $procesando = false;
    public ?array $resultado = null;
    public ?string $resumenEnriquecimiento = null;

    protected $rules = [
        'archivoEmpresas'            => 'nullable|file|mimes:csv,txt,xls,xlsx|max:20480',
        'archivoGrupos'              => 'nullable|file|mimes:csv,txt,xls,xlsx|max:20480',
        'archivoParticipantes'       => 'nullable|file|mimes:xls,xlsx|max:20480',
        'archivoAccionesFormativas'  => 'nullable|file|mimes:xls,xlsx|max:20480',
        'archivoEncuesta'            => 'nullable|file|mimes:csv,txt,xls,xlsx|max:20480',
    ];

    public function updatedArchivoEmpresas(): void
    {
        $this->validateOnly('archivoEmpresas');
    }

    public function updatedArchivoGrupos(): void
    {
        $this->validateOnly('archivoGrupos');
    }

    public function updatedArchivoParticipantes(): void
    {
        $this->validateOnly('archivoParticipantes');
    }

    public function updatedArchivoAccionesFormativas(): void
    {
        $this->validateOnly('archivoAccionesFormativas');
    }

    public function updatedArchivoEncuesta(): void
    {
        $this->validateOnly('archivoEncuesta');
    }

    public function procesar(): void
    {
        $this->validate();

        if (!$this->archivoEmpresas && !$this->archivoGrupos && !$this->archivoParticipantes && !$this->archivoAccionesFormativas && !$this->archivoEncuesta) {
            $this->addError('general', 'Debes subir al menos un archivo');
            return;
        }

        $this->procesando = true;
        $this->logs = [];
        $this->resultado = null;

        $service = new CsvImportService();
        $totalProcesados = 0;
        $totalErrores = 0;

        // Procesar empresas
        if ($this->archivoEmpresas) {
            $resultadoEmpresas = $service->importarEmpresas(
                $this->archivoEmpresas,
                $this->esAnterior
            );
            $this->logs = array_merge($this->logs, $resultadoEmpresas['logs']);
            $totalProcesados += $resultadoEmpresas['procesados'];
            $totalErrores += $resultadoEmpresas['errores'];
        }

        // Procesar grupos
        if ($this->archivoGrupos) {
            $resultadoGrupos = $service->importarGrupos(
                $this->archivoGrupos,
                $this->esAnterior
            );
            $this->logs = array_merge($this->logs, $resultadoGrupos['logs']);
            $totalProcesados += $resultadoGrupos['procesados'];
            $totalErrores += $resultadoGrupos['errores'];
        }

        // Procesar participantes bonificados (XLS)
        $participantesProcesados = false;
        if ($this->archivoParticipantes) {
            $resultadoParticipantes = $service->importarParticipantes(
                $this->archivoParticipantes
            );
            $this->logs = array_merge($this->logs, $resultadoParticipantes['logs']);
            $totalProcesados += $resultadoParticipantes['procesados'];
            $totalErrores += $resultadoParticipantes['errores'];
            $participantesProcesados = true;
        }

        // Procesar acciones formativas (XLS de FUNDAE)
        if ($this->archivoAccionesFormativas) {
            $resultadoAcciones = $service->importarAccionesFormativas(
                $this->archivoAccionesFormativas
            );
            $this->logs = array_merge($this->logs, $resultadoAcciones['logs']);
            $totalProcesados += $resultadoAcciones['procesados'];
            $totalErrores += $resultadoAcciones['errores'];
        }

        // Procesar encuestas de calidad (CSV/XLS de Microsoft Forms)
        if ($this->archivoEncuesta) {
            $resultadoEncuesta = (new EncuestaCalidadService())->importarDesdeArchivo($this->archivoEncuesta);
            $this->logs = array_merge($this->logs, $resultadoEncuesta['logs']);
            $totalProcesados += $resultadoEncuesta['procesados'];
            $totalErrores += $resultadoEncuesta['errores'];
        }

        $this->resultado = [
            'procesados' => $totalProcesados,
            'errores'    => $totalErrores,
        ];

        // Auto-enriquecimiento: tras importar participantes, sincronizar alumnos con datos del pool legacy
        if ($participantesProcesados) {
            try {
                Artisan::call('alumnos:importar-bonificados', ['--force' => true]);
                $this->resumenEnriquecimiento = trim(Artisan::output());
            } catch (\Exception $e) {
                $this->resumenEnriquecimiento = 'Error en enriquecimiento automático: ' . $e->getMessage();
            }
        }

        $this->procesando = false;
        $this->archivoEmpresas = null;
        $this->archivoGrupos = null;
        $this->archivoParticipantes = null;
        $this->archivoAccionesFormativas = null;
        $this->archivoEncuesta = null;

        $this->dispatch('import-completed');
    }

    public function limpiar(): void
    {
        $this->reset(['archivoEmpresas', 'archivoGrupos', 'archivoParticipantes', 'archivoAccionesFormativas', 'archivoEncuesta', 'logs', 'resultado', 'resumenEnriquecimiento']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.webcurso.importar-csv')
            ->layout('layouts.app', ['title' => 'Importar Archivos - WebCurso']);
    }
}
