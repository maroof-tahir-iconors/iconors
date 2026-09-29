<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asset_types';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'allowed_extensions',
        'allowed_mimes',
        'max_file_size_kb',
        'required_source_formats',
        'requires_preview',
        'generates_preview',
        'metadata_schema',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'allowed_extensions' => 'array',
        'allowed_mimes' => 'array',
        'required_source_formats' => 'array',
        'metadata_schema' => 'array',
        'requires_preview' => 'bool',
        'generates_preview' => 'bool',
        'is_active' => 'bool',
        'max_file_size_kb' => 'integer',
        'sort_order' => 'integer',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
