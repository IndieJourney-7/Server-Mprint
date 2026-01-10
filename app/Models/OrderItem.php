<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'design_id',
        'product_name',
        'price',
        'quantity',
        'subtotal',
        'product_attributes',
        'design_front_image',
        'design_back_image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'product_attributes' => 'array', // JSON
    ];

    protected $appends = ['design_front_url', 'design_back_url'];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function design()
    {
        return $this->belongsTo(UserDesign::class, 'design_id');
    }

    // Design image URL accessors
    public function getDesignFrontUrlAttribute()
    {
        if (!$this->design_front_image) return null;
        return Storage::disk('public')->url($this->design_front_image);
    }

    public function getDesignBackUrlAttribute()
    {
        if (!$this->design_back_image) return null;
        return Storage::disk('public')->url($this->design_back_image);
    }
}
