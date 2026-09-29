<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'content', 'template', 'status', 'is_system', 'published_at',
    ];

    protected $casts = [
        'is_system'    => 'bool',
        'published_at' => 'datetime',
    ];

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
