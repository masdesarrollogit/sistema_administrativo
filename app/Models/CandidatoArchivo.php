<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatoArchivo extends Model
{
    use HasFactory;

    protected $table = 'candidato_archivos';

    protected $fillable = [
        'candidato_id',
        'nombre',
        'ruta',
        'mime_type',
        'size',
    ];

    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }
}
