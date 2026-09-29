<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Format extends Model
{
    use HasFactory;

    protected $table = 'formats';

    protected $fillable = [
        'name',
        'slug',
        'mime_type',
        'is_editable',
        'is_vector',
        'supports_transparency',
        'sort_order',
    ];

    protected $casts = [
        'is_editable' => 'bool',
        'is_vector' => 'bool',
        'supports_transparency' => 'bool',
        'sort_order' => 'integer',
    ];

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_format');
    }

    public function assetFiles(): HasMany
    {
        return $this->hasMany(AssetFile::class);
    }
}
