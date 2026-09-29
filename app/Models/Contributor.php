<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contributor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contributors';

    protected $fillable = [
        'user_id',
        'display_name',
        'slug',
        'biography',
        'country',
        'website',
        'portfolio_url',
        'social_links',
        'specialties',
        'avatar',
        'cover_image',
        'status',
        'approved_at',
        'approved_by',
        'status_reason',
        'is_verified',
        'is_featured',
        'commission_rate',
        'assets_count',
        'downloads_count',
        'followers_count',
    ];

    protected $casts = [
        'social_links' => 'array',
        'specialties' => 'array',
        'approved_at' => 'datetime',
        'is_verified' => 'bool',
        'is_featured' => 'bool',
        'commission_rate' => 'decimal:4',
        'assets_count' => 'integer',
        'downloads_count' => 'integer',
        'followers_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(Pack::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function taxInfo(): HasOne
    {
        return $this->hasOne(ContributorTaxInfo::class);
    }

    /** Users following this contributor. Inverse of User::following(). */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contributor_follows')
            ->withTimestamps();
    }

    /** Per-asset-type upload permissions, in every state. */
    public function assetTypeAccess(): HasMany
    {
        return $this->hasMany(ContributorAssetTypeAccess::class);
    }

    /**
     * Asset type IDs this contributor may upload to.
     *
     * Deliberately a query rather than a cached property: upload
     * authorisation must not be served from a stale value after a
     * reviewer revokes access mid-session.
     */
    public function approvedAssetTypeIds(): array
    {
        return $this->assetTypeAccess()
            ->where('status', 'approved')
            ->pluck('asset_type_id')
            ->all();
    }

    public function canUploadType(AssetType $type): bool
    {
        return $this->assetTypeAccess()
            ->where('asset_type_id', $type->id)
            ->where('status', 'approved')
            ->exists();
    }
}
