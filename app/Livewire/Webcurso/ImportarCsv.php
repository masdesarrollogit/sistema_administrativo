<?php

namespace App\Livewire\Webcurso;

use App\Services\Webcurso\CsvImportService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportarCsv extends Component
{
    use WithFileUploads;

    public $archivoEmpresas;
    public $archivoGrupos;
    public $archivoParticipantes;
    public bool $esAnterior = false;
    public array $logs = [];
    public bool $procesando = false;
    public ?array $resultado = null;

    protected $rules = [
        'archivoEmpresas'      => 'nullable|file|mimes:csv,txt|max:10240',
        'archivoGrupos'        => 'nullable|file|mimes:csv,txt|max:10240',
        'archivoParticipantes' => 'nullable|file|mimes:xls,xlsx|max:20480',
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

    public function procesar(): void
    {
        $this->validate();

        if (!$this->archivoEmpresas && !$this->archivoGrupos && !$this->archivoParticipantes) {
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
        if ($this->archivoParticipantes) {
            $resultadoParticipantes = $service->importarParticipantes(
                $this->archivoParticipantes
            );
            $this->logs = array_merge($this->logs, $resultadoParticipantes['logs']);
            $totalProcesados += $resultadoParticipantes['procesados'];
            $totalErrores += $resultadoParticipantes['errores'];
        }

        $this->resultado = [
            'procesados' => $totalProcesados,
            'errores'    => $totalErrores,
        ];

        $this->procesando = false;
        $this->archivoEmpresas = null;
        $this->archivoGrupos = null;
        $this->archivoParticipantes = null;

        $this->dispatch('import-completed');
    }

    public function limpiar(): void
    {
        $this->reset(['archivoEmpresas', 'archivoGrupos', 'archivoParticipantes', 'logs', 'resultado']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.webcurso.importar-csv')
            ->layout('layouts.app', ['title' => 'Importar Archivos - WebCurso']);
    }
}
