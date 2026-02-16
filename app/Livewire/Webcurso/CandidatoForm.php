<?php

namespace App\Livewire\Webcurso;

use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\EmpresaExterna;
use App\Models\MoodleCurso;
use App\Models\TipoCandidato;
use Livewire\Component;

class CandidatoForm extends Component
{
    public ?Candidato $candidato = null;
    public $isEdit = false;

    // Campos del formulario
    public $tipo_candidato_id;
    public $nombre_contacto;
    public $email;
    public $telefono;
    public $curso_nombre;
    public $curso_referencia;
    public $notas;

    // Autocomplete
    public $results = [];
    public $showDropdown = false;

    // Para empresas
    public $cif_empresa;
    public $razon_social_empresa;
    public $buscar_empresa = true;

    public function updatedCursoNombre($value)
    {
        if (strlen($value) > 2) {
            $this->results = MoodleCurso::where('titulo', 'like', '%' . $value . '%')
                ->take(10)
                ->get();
            $this->showDropdown = true;
        } else {
            $this->showDropdown = false;
        }
    }

    public function seleccionarCurso($id)
    {
        $curso = MoodleCurso::find($id);
        if ($curso) {
            $this->curso_nombre = $curso->titulo;
            $this->curso_referencia = (string) $curso->horas;
            $this->showDropdown = false;
        }
    }

    protected function rules()
    {
        return [
            'tipo_candidato_id' => 'required|exists:tipos_candidato,id',
            'nombre_contacto' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'nullable|string|max:50',
            'curso_nombre' => 'nullable|string|max:255',
            'curso_referencia' => 'nullable|max:100',
            'notas' => 'nullable|string',
            'cif_empresa' => 'nullable|string|max:20',
            'razon_social_empresa' => 'nullable|string|max:255',
        ];
    }

    public function mount(?Candidato $candidato = null)
    {
        if ($candidato && $candidato->exists) {
            $this->isEdit = true;
            $this->candidato = $candidato->load(['tipoCandidato', 'empresa', 'empresaExterna']);
            
            $this->tipo_candidato_id = $candidato->tipo_candidato_id;
            $this->nombre_contacto = $candidato->nombre_contacto;
            $this->email = $candidato->email;
            $this->telefono = $candidato->telefono;
            $this->curso_nombre = $candidato->curso_nombre;
            $this->curso_referencia = $candidato->curso_referencia;
            $this->notas = $candidato->notas;

            if ($candidato->empresa) {
                $this->cif_empresa = $candidato->empresa->cif;
                $this->razon_social_empresa = $candidato->empresa->razon_social;
            } elseif ($candidato->empresaExterna) {
                $this->cif_empresa = $candidato->empresaExterna->cif;
                $this->razon_social_empresa = $candidato->empresaExterna->razon_social;
            }
        }
    }

    public function buscarEmpresaPorCif()
    {
        if (!$this->cif_empresa) {
            return;
        }

        $tipoCandidato = TipoCandidato::find($this->tipo_candidato_id);
        
        if (!$tipoCandidato) {
            return;
        }

        if ($tipoCandidato->codigo === 'empresa_organizadora') {
            $empresa = Empresa::where('cif', $this->cif_empresa)->first();
            if ($empresa) {
                $this->razon_social_empresa = $empresa->razon_social;
                session()->flash('message', 'Empresa encontrada en el sistema');
            } else {
                session()->flash('warning', 'Empresa no encontrada. Se creará una nueva.');
            }
        } elseif ($tipoCandidato->codigo === 'empresa_externa') {
            $empresaExterna = EmpresaExterna::where('cif', $this->cif_empresa)->first();
            if ($empresaExterna) {
                $this->razon_social_empresa = $empresaExterna->razon_social;
                session()->flash('message', 'Empresa externa encontrada');
            } else {
                session()->flash('warning', 'Empresa externa no encontrada. Se creará una nueva.');
            }
        }
    }

    public function save()
    {
        \Log::info('=== CandidatoForm::save() INICIADO ===', [
            'tipo_candidato_id' => $this->tipo_candidato_id,
            'nombre_contacto' => $this->nombre_contacto,
            'email' => $this->email,
            'isEdit' => $this->isEdit,
        ]);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('=== VALIDACIÓN FALLÓ ===', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        try {
            $tipoCandidato = TipoCandidato::find($this->tipo_candidato_id);
            
            $empresaId = null;
            $empresaExternaId = null;

            // Gestionar empresa según el tipo
            if ($tipoCandidato->codigo === 'empresa_organizadora' && $this->cif_empresa) {
                $empresa = Empresa::firstOrCreate(
                    ['cif' => $this->cif_empresa],
                    [
                        'razon_social' => $this->razon_social_empresa,
                        'email' => $this->email,
                        'telefono' => $this->telefono,
                    ]
                );
                $empresaId = $empresa->id;
            } elseif ($tipoCandidato->codigo === 'empresa_externa' && $this->cif_empresa) {
                $empresaExterna = EmpresaExterna::firstOrCreate(
                    ['cif' => $this->cif_empresa],
                    [
                        'razon_social' => $this->razon_social_empresa,
                        'email' => $this->email,
                        'telefono' => $this->telefono,
                        'contacto_nombre' => $this->nombre_contacto,
                    ]
                );
                $empresaExternaId = $empresaExterna->id;
            }

            $data = [
                'tipo_candidato_id' => $this->tipo_candidato_id,
                'empresa_id' => $empresaId,
                'empresa_externa_id' => $empresaExternaId,
                'nombre_contacto' => $this->nombre_contacto,
                'email' => $this->email,
                'telefono' => $this->telefono,
                'curso_nombre' => $this->curso_nombre,
                'curso_referencia' => $this->curso_referencia,
                'notas' => $this->notas,
            ];

            if ($this->isEdit) {
                $this->candidato->update($data);
                $candidato = $this->candidato;
                session()->flash('message', 'Candidato actualizado exitosamente');
            } else {
                $candidato = Candidato::create($data);
                
                // Inicializar requisitos según el tipo
                $candidato->inicializarRequisitos();
                
                session()->flash('message', 'Candidato creado exitosamente');
            }

            if (!$this->isEdit) {
                return redirect()->route('webcurso.candidatos.estatus', $candidato);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error al guardar: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $tiposCandidato = TipoCandidato::activos()->get();
        $tipoCandidatoSeleccionado = TipoCandidato::find($this->tipo_candidato_id);
        
        $requiereEmpresa = $tipoCandidatoSeleccionado && 
                          in_array($tipoCandidatoSeleccionado->codigo, ['empresa_organizadora', 'empresa_externa']);

        return view('livewire.webcurso.candidato-form', [
            'tiposCandidato' => $tiposCandidato,
            'requiereEmpresa' => $requiereEmpresa,
        ])->layout('layouts.app', ['title' => ($this->isEdit ? 'Editar' : 'Nuevo') . ' Candidato - WebCurso']);
    }

}
