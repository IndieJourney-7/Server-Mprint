<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'path',
        'description',
        'image',
        'icon',
        'banner_image',
        'sort_order',
        'is_active',
        'is_featured'
    ];

    protected $appends = [
        'image_url',
        'banner_image_url'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Accessors
    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    /**
     * Get the banner image URL for category pages
     */
    public function getBannerImageUrlAttribute()
    {
        return $this->banner_image ? asset('storage/' . $this->banner_image) : null;
    }
}
