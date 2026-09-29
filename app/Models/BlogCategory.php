<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
