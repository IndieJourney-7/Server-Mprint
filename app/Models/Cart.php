<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'design_id',
        'quantity',
        'selected_attributes',
        'front_design_path',
        'back_design_path',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'selected_attributes' => 'array',
        'unit_price' => 'float',
        'total_price' => 'float',
        'quantity' => 'integer',
    ];

    protected $appends = ['front_design_url', 'back_design_url'];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function design()
    {
        return $this->belongsTo(\App\Models\UserDesign::class, 'design_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function getFrontDesignUrlAttribute()
    {
        // First check if there's a linked design
        if ($this->design_id && $this->design) {
            // Use original URL which contains the complete card preview (with white margins)
            // NOT thumbnail_url which is cropped to 400x250
            return $this->design->front_original_url;
        }
        // Fallback to direct path
        return $this->front_design_path ? asset('storage/' . $this->front_design_path) : null;
    }

    public function getBackDesignUrlAttribute()
    {
        // First check if there's a linked design
        if ($this->design_id && $this->design) {
            // Use original URL which contains the complete card preview (with white margins)
            // NOT thumbnail_url which is cropped to 400x250
            return $this->design->back_original_url;
        }
        // Fallback to direct path
        return $this->back_design_path ? asset('storage/' . $this->back_design_path) : null;
    }
}