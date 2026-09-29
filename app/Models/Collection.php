<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collection extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'collections';

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'slug',
        'description',
        'cover_image',
        'is_public',
        'share_token',
        'assets_count',
    ];

    protected $casts = [
        'is_public' => 'bool',
        'assets_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'collection_asset')
            ->withPivot(['sort_order', 'created_at']);
    }
}
