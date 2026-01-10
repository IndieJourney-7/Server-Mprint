<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UserUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'file_path',
        'thumbnail_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'usage_count',
        'last_used_at',
        'folder',
        'is_favorite',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'usage_count' => 'integer',
        'is_favorite' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $appends = ['file_url', 'thumbnail_url'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors for full URLs
    public function getFileUrlAttribute()
    {
        if (!$this->file_path) return null;
        // Use API route for CORS support
        return url('/api/storage/' . $this->file_path);
    }

    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail_path) return null;
        // Use API route for CORS support
        return url('/api/storage/' . $this->thumbnail_path);
    }

    // Format file size for display
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    // Increment usage when image is used in a design
    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    // Delete associated files when model is deleted
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($upload) {
            if ($upload->file_path) {
                Storage::disk('public')->delete($upload->file_path);
            }
            if ($upload->thumbnail_path) {
                Storage::disk('public')->delete($upload->thumbnail_path);
            }
        });
    }

    // Scopes for common queries
    public function scopeRecent($query, $limit = 20)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopeRecentlyUsed($query, $limit = 20)
    {
        return $query->whereNotNull('last_used_at')
                     ->orderBy('last_used_at', 'desc')
                     ->limit($limit);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeInFolder($query, $folder)
    {
        return $query->where('folder', $folder);
    }
}
