<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoToken extends Model
{
    protected $table = 'zoho_tokens';

    protected $fillable = [
        'service',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'api_domain',
        'organization_id',
        'updated_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forService(string $service = 'books'): self
    {
        return static::firstOrCreate(['service' => $service]);
    }

    public function accessTokenIsValid(): bool
    {
        if (! $this->access_token || ! $this->expires_at) {
            return false;
        }

        return Carbon::now()->lt($this->expires_at->subSeconds(60));
    }
}
