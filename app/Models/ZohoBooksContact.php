<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoBooksContact extends Model
{
    protected $table = 'zoho_books_contacts';

    protected $fillable = [
        'contact_id',
        'organization_id',
        'contact_name',
        'company_name',
        'cif',
        'email',
        'phone',
        'mobile',
        'contact_type',
        'status',
        'outstanding_receivable_amount',
        'currency_code',
        'raw',
        'last_synced_at',
    ];

    protected $casts = [
        'raw'                          => 'array',
        'last_synced_at'               => 'datetime',
        'outstanding_receivable_amount'=> 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'cif', 'cif');
    }

    public function participantesBonificados(): HasMany
    {
        return $this->hasMany(ParticipanteBonificado::class, 'cif', 'cif');
    }
}
