<?php

namespace App\Livewire\Webcurso;

use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\EmpresaExterna;
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
    public $notas;

    // Para empresas
    public $cif_empresa;
    public $razon_social_empresa;
    public $buscar_empresa = true;
    public $empresaResults = [];
    public $showEmpresaDropdown = false;
    public ?bool $empresaEncontrada = null; // null=sin buscar, true=existe, false=no existe

    public function updatedRazonSocialEmpresa($value)
    {
        if (strlen($value) > 2) {
            $tipoCandidato = TipoCandidato::find($this->tipo_candidato_id);
            if (!$tipoCandidato) return;

            if ($tipoCandidato->codigo === 'empresa_organizadora') {
                $this->empresaResults = Empresa::where('razon_social', 'like', '%' . $value . '%')
                    ->take(10)
                    ->get();
            } elseif ($tipoCandidato->codigo === 'empresa_externa') {
                $this->empresaResults = EmpresaExterna::where('razon_social', 'like', '%' . $value . '%')
                    ->take(10)
                    ->get();
            }
            $this->showEmpresaDropdown = true;
        } else {
            $this->showEmpresaDropdown = false;
        }
    }

    public function seleccionarEmpresa($id)
    {
        $tipoCandidato = TipoCandidato::find($this->tipo_candidato_id);
        if (!$tipoCandidato) return;

        if ($tipoCandidato->codigo === 'empresa_organizadora') {
            $empresa = Empresa::find($id);
        } else {
            $empresa = EmpresaExterna::find($id);
        }

        if ($empresa) {
            $this->razon_social_empresa = $empresa->razon_social;
            $this->cif_empresa = $empresa->cif;
            $this->showEmpresaDropdown = false;
        }
    }

    protected function rules()
    {
        return [
            'tipo_candidato_id' => 'required|exists:tipos_candidato,id',
            'nombre_contacto' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'nullable|string|max:50',
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
            $this->notas = $candidato->notas;

            if ($candidato->empresa) {
                $this->cif_empresa = $candidato->empresa->cif;
                $this->razon_social_empresa = $candidato->empresa->razon_social;
                $this->empresaEncontrada = true;
            } elseif ($candidato->empresaExterna) {
                $this->cif_empresa = $candidato->empresaExterna->cif;
                $this->razon_social_empresa = $candidato->empresaExterna->razon_social;
                $this->empresaEncontrada = true;
            }
        }
    }

    /**
     * Búsqueda en vivo al escribir el CIF: rellena la razón social si la empresa
     * existe. Si no existe, deja la razón social vacía y marca el aviso.
     * NUNCA crea la empresa.
     */
    public function updatedCifEmpresa($value): void
    {
        $this->buscarEmpresaPorCif();
    }

    public function buscarEmpresaPorCif(): void
    {
        $tipoCandidato = TipoCandidato::find($this->tipo_candidato_id);

        // Solo las bonificadas se buscan: son las únicas que registramos en `empresas`
        // porque les calculamos el crédito. Las externas se teclean a mano.
        if ($tipoCandidato?->codigo !== 'empresa_organizadora') {
            return;
        }

        if (!trim((string) $this->cif_empresa)) {
            $this->empresaEncontrada = null;
            $this->razon_social_empresa = '';
            return;
        }

        $empresa = $this->buscarEmpresaModelo($tipoCandidato->codigo, $this->cif_empresa);

        if ($empresa) {
            $this->razon_social_empresa = $empresa->razon_social;
            $this->empresaEncontrada = true;
            $this->resetErrorBag('cif_empresa');
        } else {
            $this->razon_social_empresa = '';
            $this->empresaEncontrada = false;
        }
    }

    /** Busca la empresa por CIF normalizado (mayúsculas, sin espacios/guiones/puntos). NO crea. */
    private function buscarEmpresaModelo(string $codigo, ?string $cif)
    {
        $cifNorm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim((string) $cif)));
        if ($cifNorm === '') {
            return null;
        }

        $query = $codigo === 'empresa_organizadora' ? Empresa::query() : EmpresaExterna::query();

        return $query
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(cif, '-', ''), ' ', ''), '.', '')) = ?", [$cifNorm])
            ->first();
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

            // Empresa BONIFICADA: debe existir ya en `empresas`, porque su crédito FUNDAE
            // lo calculamos nosotros a partir del XLS. NUNCA se crea desde aquí.
            if ($tipoCandidato->codigo === 'empresa_organizadora') {
                if (!trim((string) $this->cif_empresa)) {
                    $this->addError('cif_empresa', 'El CIF es obligatorio.');
                    return;
                }

                $empresaEncontrada = $this->buscarEmpresaModelo('empresa_organizadora', $this->cif_empresa);

                if (!$empresaEncontrada) {
                    $this->empresaEncontrada = false;
                    $this->addError('cif_empresa', 'No existe ninguna empresa registrada con ese CIF. Regístrala primero — el candidato no se creará.');
                    return;
                }

                // Sincroniza la razón social mostrada con la de la empresa real
                $this->razon_social_empresa = $empresaEncontrada->razon_social;
                $empresaId = $empresaEncontrada->id;
            }

            // Empresa EXTERNA: gestiona su propia bonificación, no calculamos su saldo y
            // por tanto no entra en `empresas`. Sus datos se teclean y se guardan en
            // `empresas_externas`, que es solo un registro nuestro de control.
            if ($tipoCandidato->codigo === 'empresa_externa') {
                $cif = strtoupper(preg_replace('/[\s\-\.]+/', '', trim((string) $this->cif_empresa)));
                $razonSocial = trim((string) $this->razon_social_empresa);

                if ($cif === '') {
                    $this->addError('cif_empresa', 'El CIF de la empresa es obligatorio.');
                    return;
                }
                if ($razonSocial === '') {
                    $this->addError('razon_social_empresa', 'La razón social es obligatoria.');
                    return;
                }

                $externa = EmpresaExterna::updateOrCreate(
                    ['cif' => $cif],
                    ['razon_social' => $razonSocial],
                );

                $this->cif_empresa = $cif;
                $empresaExternaId = $externa->id;
            }

            $data = [
                'tipo_candidato_id' => $this->tipo_candidato_id,
                'empresa_id' => $empresaId,
                'empresa_externa_id' => $empresaExternaId,
                'nombre_contacto' => $this->nombre_contacto,
                'email' => $this->email,
                'telefono' => $this->telefono,
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
        
        $codigo = $tipoCandidatoSeleccionado?->codigo;

        return view('livewire.webcurso.candidato-form', [
            'tiposCandidato' => $tiposCandidato,
            'requiereEmpresa' => in_array($codigo, ['empresa_organizadora', 'empresa_externa']),
            // Bonificada: la empresa debe estar en `empresas`, porque le calculamos el crédito
            // FUNDAE. Por eso se busca por CIF y no se puede crear desde aquí.
            'buscaEmpresaRegistrada' => $codigo === 'empresa_organizadora',
            // Externa: gestiona su propia bonificación y nosotros no calculamos su saldo,
            // así que NO entra en `empresas`. Se teclean sus datos y se guardan en
            // `empresas_externas`, que es un registro nuestro de control.
            'empresaExternaLibre' => $codigo === 'empresa_externa',
        ])->layout('layouts.app', ['title' => ($this->isEdit ? 'Editar' : 'Nuevo') . ' Candidato - WebCurso']);
    }

}
