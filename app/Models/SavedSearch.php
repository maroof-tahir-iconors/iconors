<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    use HasFactory;

    protected $table = 'saved_searches';

    protected $fillable = [
        'user_id',
        'name',
        'query',
        'filters',
        'notify_new_matches',
    ];

    protected $casts = [
        'filters' => 'array',
        'notify_new_matches' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
