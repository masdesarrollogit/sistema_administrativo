<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodleCurso extends Model
{
    protected $table = 'moodle_cursos';

    protected $fillable = [
        'moodle_categoria_id',
        'titulo',
        'precio',
        'horas'
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'horas' => 'integer'
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(MoodleCategoria::class, 'moodle_categoria_id');
    }
}
