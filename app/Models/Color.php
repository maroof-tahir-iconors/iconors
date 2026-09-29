<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Color extends Model
{
    use HasFactory;

    protected $table = 'colors';

    protected $fillable = [
        'name',
        'slug',
        'hex',
        'hue',
        'saturation',
        'lightness',
        'sort_order',
    ];

    protected $casts = [
        'hue' => 'integer',
        'saturation' => 'integer',
        'lightness' => 'integer',
        'sort_order' => 'integer',
    ];

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_color')
            ->withPivot('coverage_percent');
    }
}
