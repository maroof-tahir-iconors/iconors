<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetFile extends Model
{
    use HasFactory;

    protected $table = 'asset_files';

    protected $fillable = [
        'asset_id',
        'format_id',
        'disk',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'size_bytes',
        'checksum',
        'is_source',
        'requires_purchase',
        'scan_status',
        'scanned_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'is_source' => 'bool',
        'requires_purchase' => 'bool',
        'scanned_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }
}
