<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributorApplication extends Model
{
    use HasFactory;

    protected $table = 'contributor_applications';

    protected $fillable = [
        'user_id',
        'full_name',
        'display_name',
        'country',
        'biography',
        'portfolio_url',
        'website',
        'social_links',
        'specialties',
        'sample_files',
        'payout_preference',
        'terms_accepted',
        'terms_accepted_at',
        'status',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'social_links' => 'array',
        'specialties' => 'array',
        'sample_files' => 'array',
        'terms_accepted' => 'bool',
        'terms_accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
