<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoodleCategoria extends Model
{
    protected $table = 'moodle_categorias';

    protected $fillable = ['nombre'];

    public function cursos(): HasMany
    {
        return $this->hasMany(MoodleCurso::class, 'moodle_categoria_id');
    }
}
