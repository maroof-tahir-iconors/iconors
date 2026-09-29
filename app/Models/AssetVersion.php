<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetVersion extends Model
{
    use HasFactory;

    protected $table = 'asset_versions';

    protected $fillable = [
        'asset_id',
        'version',
        'changelog',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
