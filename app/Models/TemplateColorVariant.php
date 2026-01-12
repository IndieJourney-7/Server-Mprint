<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateColorVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'color_name',
        'color_hex',
        'preview_image',
        'front_template_path',
        'back_template_path',
        'sort_order',
    ];

    protected $appends = [
        'preview_url',
        'front_template_url',
        'back_template_url',
    ];

    // Relationships
    public function template()
    {
        return $this->belongsTo(Template::class);
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
}
