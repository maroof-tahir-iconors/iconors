<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assets';

    protected $fillable = [
        'contributor_id',
        'asset_type_id',
        'category_id',
        'style_id',
        'license_id',
        'pack_id',
        'title',
        'slug',
        'description',
        'is_premium',
        'price',
        'currency',
        'subscription_only',
        'is_animated',
        'is_3d',
        'has_transparency',
        'orientation',
        'metadata',
        'dominant_colors',
        'status',
        'status_reason',
        'reviewed_by',
        'reviewed_at',
        'published_at',
        'scheduled_for',
        'is_featured',
        'views_count',
        'downloads_count',
        'likes_count',
        'sales_count',
        'rating_average',
        'rating_count',
    ];

    protected $casts = [
        'is_premium' => 'bool',
        'price' => 'decimal:4',
        'subscription_only' => 'bool',
        'is_animated' => 'bool',
        'is_3d' => 'bool',
        'has_transparency' => 'bool',
        'metadata' => 'array',
        'dominant_colors' => 'array',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'is_featured' => 'bool',
        'views_count' => 'integer',
        'downloads_count' => 'integer',
        'likes_count' => 'integer',
        'sales_count' => 'integer',
        'rating_average' => 'decimal:4',
        'rating_count' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AssetFile::class);
    }

    public function previews(): HasMany
    {
        return $this->hasMany(AssetPreview::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssetVersion::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'asset_tag');
    }

    public function formats(): BelongsToMany
    {
        return $this->belongsToMany(Format::class, 'asset_format');
    }

    public function softwares(): BelongsToMany
    {
        return $this->belongsToMany(Software::class, 'asset_software');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class, 'asset_industry');
    }

    public function useCases(): BelongsToMany
    {
        return $this->belongsToMany(UseCase::class, 'asset_use_case', 'asset_id', 'use_case_id');
    }

    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'asset_color')
            ->withPivot('coverage_percent');
    }

    public function packs(): BelongsToMany
    {
        return $this->belongsToMany(Pack::class, 'pack_asset')
            ->withPivot('sort_order');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_asset')
            ->withPivot('sort_order');
    }
}
