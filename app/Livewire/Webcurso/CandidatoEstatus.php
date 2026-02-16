<?php

namespace App\Livewire\Webcurso;

use App\Models\Candidato;
use App\Models\RequisitoCandidato;
use App\Models\CandidatoArchivo;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidatoEstatus extends Component
{
    use WithFileUploads;

    public Candidato $candidato;
    public $notas = [];
    
    // Propiedades de Configuración de Envío y Observación
    public $fecha_inicio;
    public $frecuencia_envio;
    public $descripcion_personalizada;
    public $observacion;
    
    // Archivos
    public $archivos = [];
    public $archivosGuardados = [];

    public function mount(Candidato $candidato)
    {
        $this->candidato = $candidato->load(['requisitos.tipoRequisito', 'tipoCandidato', 'empresa', 'empresaExterna', 'archivos']);
        
        // Inicializar configuración
        $this->fecha_inicio = $candidato->fecha_inicio ? $candidato->fecha_inicio->format('Y-m-d') : null;
        $this->frecuencia_envio = $candidato->frecuencia_envio ?? 3;
        $this->descripcion_personalizada = $candidato->descripcion_personalizada ?? '';
        $this->observacion = $candidato->observacion ?? '';
        
        $this->archivosGuardados = $candidato->archivos;

        // Inicializar notas requisitos
        foreach ($this->candidato->requisitos as $requisito) {
            $this->notas[$requisito->id] = $requisito->notas ?? '';
        }
    }

    public function guardarObservacion()
    {
        $this->validate([
            'observacion' => 'nullable|string',
        ]);

        $this->candidato->update([
            'observacion' => $this->observacion,
        ]);

        session()->flash('message', 'Observación guardada correctamente');
    }

    public function actualizarConfiguracion()
    {
        $this->validate([
            'fecha_inicio' => 'nullable|date',
            'frecuencia_envio' => 'required|integer|min:1',
            'descripcion_personalizada' => 'nullable|string',
            'observacion' => 'nullable|string',
            'archivos.*' => 'nullable|file|max:10240', // 10MB máx
        ]);

        $this->candidato->update([
            'fecha_inicio' => $this->fecha_inicio,
            'frecuencia_envio' => $this->frecuencia_envio,
            'descripcion_personalizada' => $this->descripcion_personalizada,
            'observacion' => $this->observacion,
        ]);

        // Procesar archivos nuevos
        if ($this->archivos) {
            foreach ($this->archivos as $archivo) {
                $nombreOriginal = $archivo->getClientOriginalName();
                $ruta = $archivo->store('candidatos/adjuntos', 'public');
                
                CandidatoArchivo::create([
                    'candidato_id' => $this->candidato->id,
                    'nombre' => $nombreOriginal,
                    'ruta' => $ruta,
                    'mime_type' => $archivo->getMimeType(),
                    'size' => $archivo->getSize(),
                ]);
            }
            $this->archivos = [];
            $this->archivosGuardados = $this->candidato->archivos()->get();
        }

        session()->flash('message', 'Configuración y archivos actualizados correctamente');
    }

    public function eliminarArchivo($archivoId)
    {
        $archivo = CandidatoArchivo::find($archivoId);
        if ($archivo && $archivo->candidato_id === $this->candidato->id) {
            Storage::disk('public')->delete($archivo->ruta);
            $archivo->delete();
            $this->archivosGuardados = $this->candidato->archivos()->get();
            session()->flash('message', 'Archivo eliminado');
        }
    }

    public function marcarCompletado($requisitoId)
    {
        $requisito = RequisitoCandidato::find($requisitoId);
        
        if ($requisito && $requisito->candidato_id === $this->candidato->id) {
            $requisito->marcarCompletado($this->notas[$requisitoId] ?? null);
            
            session()->flash('message', 'Requisito marcado como completado');
            
            // Recargar candidato
            $this->candidato->refresh();
        }
    }

    public function marcarPendiente($requisitoId)
    {
        $requisito = RequisitoCandidato::find($requisitoId);
        
        if ($requisito && $requisito->candidato_id === $this->candidato->id) {
            $requisito->update([
                'estado' => 'pendiente',
                'fecha_completado' => null,
            ]);
            
            session()->flash('message', 'Requisito marcado como pendiente');
            
            // Recargar candidato
            $this->candidato->refresh();
        }
    }

    public function marcarEnProceso($requisitoId)
    {
        $requisito = RequisitoCandidato::find($requisitoId);
        
        if ($requisito && $requisito->candidato_id === $this->candidato->id) {
            $requisito->update(['estado' => 'en_proceso']);
            
            session()->flash('message', 'Requisito marcado como en proceso');
            
            // Recargar candidato
            $this->candidato->refresh();
        }
    }

    public function pausarCandidato()
    {
        $this->candidato->pausar();
        session()->flash('message', 'Candidato pausado - No recibirá más recordatorios');
        $this->candidato->refresh();
    }

    public function reactivarCandidato()
    {
        $this->candidato->reactivar();
        session()->flash('message', 'Candidato reactivado - Contador de recordatorios reiniciado');
        $this->candidato->refresh();
    }

    public function desactivarCandidato()
    {
        $this->candidato->update(['estatus' => 'desactivado']);
        session()->flash('message', 'Candidato desactivado');
        $this->candidato->refresh();
    }

    public function guardarNotas($requisitoId)
    {
        $requisito = RequisitoCandidato::find($requisitoId);
        
        if ($requisito && $requisito->candidato_id === $this->candidato->id) {
            $requisito->update(['notas' => $this->notas[$requisitoId] ?? '']);
            session()->flash('message', 'Notas guardadas');
        }
    }

    public function render()
    {
        return view('livewire.webcurso.candidato-estatus')
            ->layout('layouts.app', ['title' => 'Gestionar Candidato - WebCurso']);
    }

}
