<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'teams';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'logo',
        'brand_colors',
        'asset_preferences',
        'seats_total',
        'seats_used',
        'is_active',
    ];

    protected $casts = [
        'brand_colors' => 'array',
        'asset_preferences' => 'array',
        'seats_total' => 'integer',
        'seats_used' => 'integer',
        'is_active' => 'bool',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
