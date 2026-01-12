<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'subcategory_id',
        'preview_image',
        'front_template_path',
        'back_template_path',
        'orientation',
        'corners',
        'available_colors',
        'customizable_fields',
        'base_price',
        'is_active',
        'is_featured',
        'sort_order',
        'usage_count',
        'print_width_inches',
        'print_length_inches',
    ];

    protected $casts = [
        'available_colors' => 'array',
        'customizable_fields' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'base_price' => 'decimal:2',
        'print_width_inches' => 'decimal:2',
        'print_length_inches' => 'decimal:2',
    ];

    protected $appends = [
        'preview_url',
        'front_template_url',
        'back_template_url',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function colorVariants()
    {
        return $this->hasMany(TemplateColorVariant::class)->orderBy('sort_order');
    }

    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_favorite_templates')
            ->withTimestamps();
    }

    // Accessors for URLs with CORS support
    public function getPreviewUrlAttribute()
    {
        if (!$this->preview_image) return null;
        return url('/api/storage/' . $this->preview_image);
    }

    public function getFrontTemplateUrlAttribute()
    {
        if (!$this->front_template_path) return null;
        return url('/api/storage/' . $this->front_template_path);
    }

    public function getBackTemplateUrlAttribute()
    {
        if (!$this->back_template_path) return null;
        return url('/api/storage/' . $this->back_template_path);
    }

    // Check if user has favorited this template
    public function isFavoritedBy($userId)
    {
        return $this->favoritedByUsers()->where('user_id', $userId)->exists();
    }

    // Increment usage count
    public function incrementUsage()
    {
        $this->increment('usage_count');
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

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByOrientation($query, $orientation)
    {
        return $query->where('orientation', $orientation);
    }

    public function scopeByCorners($query, $corners)
    {
        return $query->where('corners', $corners);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%");
        });
    }
}
