<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumnoCalificacionMoodle extends Model
{
    use HasFactory;

    protected $table = 'alumno_calificaciones_moodle';

    protected $fillable = [
        'alumno_progreso_moodle_id',
        'moodle_grade_item_id',
        'itemname',
        'itemtype',
        'itemmodule',
        'grade',
        'grademax',
        'grademin',
        'percentageformatted',
        'lettergrade',
        'is_course_total',
        'is_final_quiz',
        'graded_at',
    ];

    protected $casts = [
        'moodle_grade_item_id' => 'integer',
        'grade' => 'decimal:2',
        'grademax' => 'decimal:2',
        'grademin' => 'decimal:2',
        'is_course_total' => 'boolean',
        'is_final_quiz' => 'boolean',
        'graded_at' => 'integer',
    ];

    public function progreso(): BelongsTo
    {
        return $this->belongsTo(AlumnoProgresoMoodle::class, 'alumno_progreso_moodle_id');
    }

    public function getPorcentajeAttribute(): ?float
    {
        if ($this->grade === null || $this->grademax === null || (float) $this->grademax <= 0) {
            return null;
        }
        return round(((float) $this->grade / (float) $this->grademax) * 100, 2);
    }
}
