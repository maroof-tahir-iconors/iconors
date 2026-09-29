<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pack extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'packs';

    protected $fillable = [
        'contributor_id',
        'title',
        'slug',
        'description',
        'cover_image',
        'is_premium',
        'price',
        'currency',
        'status',
        'published_at',
        'scheduled_for',
        'is_featured',
        'assets_count',
        'downloads_count',
    ];

    protected $casts = [
        'is_premium' => 'bool',
        'price' => 'decimal:4',
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'is_featured' => 'bool',
        'assets_count' => 'integer',
        'downloads_count' => 'integer',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function packAssets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'pack_asset')
            ->withPivot('sort_order');
    }
}
