<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaEmpresa extends Model
{
    protected $table = 'nota_empresas';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'title',
        'body',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->withDefault(['name' => 'Usuario eliminado']);
    }

    public function esPropia(): bool
    {
        return $this->user_id !== null && $this->user_id === auth()->id();
    }
}
