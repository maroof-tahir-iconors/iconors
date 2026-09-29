<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-content SEO overrides — brief §29.
 *
 * Polymorphic so pages, posts, assets, packs, categories and contributors
 * all share one table rather than six near-identical column sets.
 */
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type', 'seoable_id', 'title', 'description', 'canonical_url',
        'focus_keywords', 'is_indexable', 'is_followable',
        'og_title', 'og_description', 'og_image', 'twitter_card',
        'schema_type', 'schema_overrides',
    ];

    protected $casts = [
        'focus_keywords'   => 'array',
        'schema_overrides' => 'array',
        'is_indexable'     => 'bool',
        'is_followable'    => 'bool',
    ];

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
