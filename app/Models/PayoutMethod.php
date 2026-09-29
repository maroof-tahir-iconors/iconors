<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayoutMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payout_methods';

    protected $fillable = [
        'contributor_id',
        'type',
        'details',
        'display_hint',
        'is_default',
        'is_verified',
    ];

    protected $casts = [
        'details' => 'encrypted',
        'is_default' => 'bool',
        'is_verified' => 'bool',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }
}
