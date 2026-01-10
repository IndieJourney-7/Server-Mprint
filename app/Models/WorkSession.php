<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'product_id',
        'design_id',
        'orientation',
        'shape',
        'selected_attributes',
        'front_canvas_state',
        'back_canvas_state',
        'front_thumbnail',
        'back_thumbnail',
        'last_saved_at',
    ];

    protected $casts = [
        'selected_attributes' => 'array',
        'front_canvas_state' => 'array',
        'back_canvas_state' => 'array',
        'last_saved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (!$session->session_id) {
                $session->session_id = 'ws_' . Str::uuid();
            }
            $session->last_saved_at = now();
        });

        static::updating(function ($session) {
            $session->last_saved_at = now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function design()
    {
        return $this->belongsTo(UserDesign::class, 'design_id');
    }
}
