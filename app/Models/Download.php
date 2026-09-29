<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    protected $fillable = [
        'user_id', 'asset_id', 'asset_file_id', 'contributor_id', 'team_id',
        'order_id', 'access_type', 'license_id', 'ip_address', 'user_agent',
        'country', 'is_flagged', 'flag_reason',
    ];

    protected $casts = [
        'is_flagged' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetFile(): BelongsTo
    {
        return $this->belongsTo(AssetFile::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }
}
