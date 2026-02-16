<?php

namespace App\Traits;

use App\Mail\SaldoEmpresaMail;
use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use App\Models\Candidato;
use App\Models\TipoCandidato;
use Illuminate\Support\Facades\Mail;

trait HasEmpresaModal
{
    // Modal empresa (Candidato + Saldo)
    public bool $mostrarModalEmpresa = false;
    public string $nombreCandidato = '';
    public string $emailCandidato = '';
    public ?string $mensajeCandidato = null;
    public ?array $empresaParaEmail = null;
    public bool $enviandoEmail = false;
    public ?string $mensajeEmail = null;

    public function abrirModalEmpresa(int $id): void
    {
        $modelo = $this->anioSeleccionado === $this->anioAnterior 
            ? EmpresaAnterior::class 
            : Empresa::class;
        
        $empresa = $modelo::find($id);
        
        if ($empresa) {
            $this->empresaParaEmail = $empresa->toArray();
            // Aseguramos que el saldo formateado esté disponible
            $this->empresaParaEmail['saldo_formateado'] = $empresa->saldo_formateado;
            
            $this->mostrarModalEmpresa = true;
            $this->nombreCandidato = '';
            $this->emailCandidato = '';
            $this->mensajeCandidato = null;
            $this->mensajeEmail = null;
        }
    }

    public function cerrarModalEmpresa(): void
    {
        $this->mostrarModalEmpresa = false;
        $this->empresaParaEmail = null;
        $this->nombreCandidato = '';
        $this->emailCandidato = '';
        $this->mensajeCandidato = null;
        $this->mensajeEmail = null;
    }

    public function guardarCandidato(): void
    {
        if (!$this->empresaParaEmail) return;

        $this->validate([
            'nombreCandidato' => 'required|string|min:3',
            'emailCandidato' => 'required|email',
        ]);

        try {
            $tipo = TipoCandidato::where('codigo', 'empresa_organizadora')->first();
            
            if (!$tipo) {
                throw new \Exception('Tipo de candidato "empresa_organizadora" no encontrado.');
            }

            $candidato = Candidato::create([
                'tipo_candidato_id' => $tipo->id,
                'empresa_id' => $this->empresaParaEmail['id'],
                'nombre_contacto' => $this->nombreCandidato,
                'email' => $this->emailCandidato,
                'estatus' => 'pendiente',
            ]);

            $candidato->inicializarRequisitos();

            $this->mensajeCandidato = '✅ Candidato registrado correctamente';
            $this->nombreCandidato = '';
            $this->emailCandidato = '';
        } catch (\Exception $e) {
            $this->mensajeCandidato = '❌ Error: ' . $e->getMessage();
        }
    }

    public function enviarSaldo(): void
    {
        if (!$this->empresaParaEmail) {
            return;
        }

        $this->enviandoEmail = true;

        try {
            $destinatarios = config('candidatos.notificaciones.destinatarios_saldo', []);

            Mail::to($destinatarios)
                ->send(new SaldoEmpresaMail(
                    $this->empresaParaEmail['cif'],
                    $this->empresaParaEmail['razon_social'],
                    $this->empresaParaEmail['saldo_formateado']
                ));

            $this->mensajeEmail = '✅ Correo enviado correctamente';
        } catch (\Exception $e) {
            $this->mensajeEmail = '❌ Error al enviar: ' . $e->getMessage();
        }

        $this->enviandoEmail = false;
    }
}
