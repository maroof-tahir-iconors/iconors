<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPreview extends Model
{
    use HasFactory;

    protected $table = 'asset_previews';

    protected $fillable = [
        'asset_id',
        'type',
        'disk',
        'path',
        'width',
        'height',
        'size_bytes',
        'is_watermarked',
        'is_primary',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size_bytes' => 'integer',
        'is_watermarked' => 'bool',
        'is_primary' => 'bool',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
