<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Style extends Model
{
    use HasFactory;

    protected $table = 'styles';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'preview_image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'integer',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
