<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_annual',
        'currency',
        'stripe_monthly_price_id',
        'stripe_annual_price_id',
        'trial_days',
        'download_limit_daily',
        'download_limit_monthly',
        'credits_monthly',
        'team_seats',
        'premium_access',
        'source_file_access',
        'editor_access',
        'api_access',
        'allowed_asset_types',
        'license_id',
        'is_team_plan',
        'is_featured',
        'is_active',
        'is_public',
        'available_countries',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:4',
        'price_annual' => 'decimal:4',
        'trial_days' => 'integer',
        'download_limit_daily' => 'integer',
        'download_limit_monthly' => 'integer',
        'credits_monthly' => 'integer',
        'team_seats' => 'integer',
        'premium_access' => 'bool',
        'source_file_access' => 'bool',
        'editor_access' => 'bool',
        'api_access' => 'bool',
        'allowed_asset_types' => 'array',
        'is_team_plan' => 'bool',
        'is_featured' => 'bool',
        'is_active' => 'bool',
        'is_public' => 'bool',
        'available_countries' => 'array',
        'sort_order' => 'integer',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }
}
