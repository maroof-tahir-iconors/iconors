<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasFactory;

    protected $table = 'licenses';

    protected $fillable = [
        'name',
        'slug',
        'summary',
        'full_text',
        'allows_commercial',
        'allows_modification',
        'allows_redistribution',
        'requires_attribution',
        'is_extended',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'allows_commercial' => 'bool',
        'allows_modification' => 'bool',
        'allows_redistribution' => 'bool',
        'requires_attribution' => 'bool',
        'is_extended' => 'bool',
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }
}
