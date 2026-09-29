<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasFactory;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id',
        'label',
        'value',
        'is_highlighted',
        'is_included',
        'sort_order',
    ];

    protected $casts = [
        'is_highlighted' => 'bool',
        'is_included' => 'bool',
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
