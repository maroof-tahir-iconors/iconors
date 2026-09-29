<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributorTaxInfo extends Model
{
    use HasFactory;

    protected $table = 'contributor_tax_info';

    protected $fillable = [
        'contributor_id',
        'legal_name',
        'entity_type',
        'country',
        'tax_id',
        'address',
        'tax_form_type',
        'withholding_rate',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'tax_id' => 'encrypted',
        'address' => 'encrypted',
        'withholding_rate' => 'decimal:4',
        'is_verified' => 'bool',
        'verified_at' => 'datetime',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }
}
