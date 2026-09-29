<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contributor's permission to upload one asset type.
 *
 * Absence of a row means no access. Only `approved` grants upload, so a
 * `rejected` or `revoked` row is as good as none for permission purposes
 * while still preserving the history a reviewer needs.
 */
class ContributorAssetTypeAccess extends Model
{
    use HasFactory;

    protected $table = 'contributor_asset_type_access';

    protected $fillable = [
        'contributor_id',
        'asset_type_id',
        'status',
        'portfolio_url',
        'note',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * True while the contributor is waiting on a decision — the card
     * shows "Requested" rather than offering the request form again.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['requested', 'under_review'], true);
    }

    /**
     * Whether a fresh request is allowed. A rejection is not permanent:
     * a contributor may improve their portfolio and ask again.
     */
    public function canRequestAgain(): bool
    {
        return in_array($this->status, ['rejected', 'revoked'], true);
    }
}
