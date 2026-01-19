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
            // PRIORITY ORDER for Cart display:
            // 1. front_preview_url - Final preview WITH text layers baked in (saved during finalize)
            // 2. front_original_url - Raw uploaded image (fallback if no preview)
            // This ensures text edits are visible in the Cart
            if ($this->design->front_preview_url) {
                return $this->design->front_preview_url;
            }
            return $this->design->front_original_url;
        }
        // Fallback to direct path
        return $this->front_design_path ? asset('storage/' . $this->front_design_path) : null;
    }

    public function getBackDesignUrlAttribute()
    {
        // First check if there's a linked design
        if ($this->design_id && $this->design) {
            // PRIORITY ORDER for Cart display:
            // 1. back_preview_url - Final preview WITH text layers baked in (saved during finalize)
            // 2. back_original_url - Raw uploaded image (fallback if no preview)
            // This ensures text edits are visible in the Cart
            if ($this->design->back_preview_url) {
                return $this->design->back_preview_url;
            }
            return $this->design->back_original_url;
        }
        // Fallback to direct path
        return $this->back_design_path ? asset('storage/' . $this->back_design_path) : null;
    }
}