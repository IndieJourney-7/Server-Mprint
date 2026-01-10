<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UserDesign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'quantity',
        'selected_shape',
        'selected_finishing',
        'name',
        'front_original_path',
        'front_thumbnail_path',
        'front_position_data',
        'front_canvas_state',
        'back_original_path',
        'back_thumbnail_path',
        'back_position_data',
        'back_canvas_state',
        'front_final_path',
        'back_final_path',
        'orientation',
        'status',
        'order_id',
        'ordered_at',
    ];

    protected $casts = [
        'front_position_data' => 'array',
        'back_position_data' => 'array',
        'front_canvas_state' => 'array',
        'back_canvas_state' => 'array',
        'ordered_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'design_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'design_id');
    }

    // Accessors for full URLs
    public function getFrontOriginalUrlAttribute()
    {
        if (!$this->front_original_path) return null;
        // Use API route for CORS support
        return url('/api/storage/' . $this->front_original_path);
    }

    public function getFrontThumbnailUrlAttribute()
    {
        if (!$this->front_thumbnail_path) return null;
        // Use API route for CORS support
        return url('/api/storage/' . $this->front_thumbnail_path);
    }

    public function getBackOriginalUrlAttribute()
    {
        if (!$this->back_original_path) return null;
        // Use API route for CORS support
        return url('/api/storage/' . $this->back_original_path);
    }

    public function getBackThumbnailUrlAttribute()
    {
        if (!$this->back_thumbnail_path) return null;
        // Use API route for CORS support
        return url('/api/storage/' . $this->back_thumbnail_path);
    }

    // Final image URLs (for printing/ordering)
    public function getFrontFinalUrlAttribute()
    {
        if (!$this->front_final_path) return null;
        return Storage::disk('public')->url($this->front_final_path);
    }

    public function getBackFinalUrlAttribute()
    {
        if (!$this->back_final_path) return null;
        return Storage::disk('public')->url($this->back_final_path);
    }

    // Helper to check if design has content
    public function getHasFrontDesignAttribute()
    {
        return !empty($this->front_original_path);
    }

    public function getHasBackDesignAttribute()
    {
        return !empty($this->back_original_path);
    }

    public function getHasAnyDesignAttribute()
    {
        return $this->has_front_design || $this->has_back_design;
    }

    // Delete associated files when model is deleted
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($design) {
            // Delete front design files
            if ($design->front_original_path) {
                Storage::disk('public')->delete($design->front_original_path);
            }
            if ($design->front_thumbnail_path) {
                Storage::disk('public')->delete($design->front_thumbnail_path);
            }

            // Delete back design files
            if ($design->back_original_path) {
                Storage::disk('public')->delete($design->back_original_path);
            }
            if ($design->back_thumbnail_path) {
                Storage::disk('public')->delete($design->back_thumbnail_path);
            }

            // Delete final images
            if ($design->front_final_path) {
                Storage::disk('public')->delete($design->front_final_path);
            }
            if ($design->back_final_path) {
                Storage::disk('public')->delete($design->back_final_path);
            }
        });
    }

    // Append URLs to JSON output
    protected $appends = [
        'front_original_url',
        'front_thumbnail_url',
        'back_original_url',
        'back_thumbnail_url',
        'front_final_url',
        'back_final_url',
        'has_front_design',
        'has_back_design',
        'has_any_design',
    ];
}
