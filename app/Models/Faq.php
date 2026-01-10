<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'category',
        'sort_order',
        'is_active',
        'is_featured',
        'views',
        'helpful_count',
        'not_helpful_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'views' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    // FAQ Categories for printing e-commerce
    public static $categories = [
        'ordering' => 'Ordering & Checkout',
        'design' => 'Design & Artwork',
        'printing' => 'Printing & Quality',
        'shipping' => 'Shipping & Delivery',
        'returns' => 'Returns & Refunds',
        'payment' => 'Payment & Billing',
        'account' => 'Account & Login',
        'bulk' => 'Bulk Orders',
        'general' => 'General',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getCategoryLabelAttribute()
    {
        return self::$categories[$this->category] ?? 'General';
    }

    public function getHelpfulPercentageAttribute()
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        if ($total === 0) return 0;
        return round(($this->helpful_count / $total) * 100);
    }

    // Methods
    public function incrementViews()
    {
        $this->increment('views');
    }

    public function markHelpful()
    {
        $this->increment('helpful_count');
    }

    public function markNotHelpful()
    {
        $this->increment('not_helpful_count');
    }
}
