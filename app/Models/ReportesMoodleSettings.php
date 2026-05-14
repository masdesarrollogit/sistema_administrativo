<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportesMoodleSettings extends Model
{
    protected $table = 'reportes_moodle_settings';

    protected $fillable = [
        'notificaciones_activas',
        'updated_by',
    ];

    protected $casts = [
        'notificaciones_activas' => 'bool',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function actual(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['notificaciones_activas' => true],
        );
    }

    public static function notificacionesActivas(): bool
    {
        return (bool) static::actual()->notificaciones_activas;
    }

    public function cambiarEstado(bool $activo, ?int $userId): void
    {
        $this->notificaciones_activas = $activo;
        $this->updated_by = $userId;
        $this->save();
    }
}
