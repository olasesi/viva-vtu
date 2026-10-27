<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'faq_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'views',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'views' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
