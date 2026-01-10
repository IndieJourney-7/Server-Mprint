<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'content',
        'meta_title',
        'meta_description',
        'is_active',
        'last_updated_at',
        'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Policy types available
     */
    public const TYPES = [
        'terms' => 'Terms and Conditions',
        'privacy' => 'Privacy Policy',
        'refund' => 'Refund Policy',
        'shipping' => 'Shipping Policy',
    ];

    /**
     * Get policy type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Scope for active policies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for finding by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get policy by type
     */
    public static function getByType($type)
    {
        return static::active()->ofType($type)->first();
    }

    /**
     * Update version on content change
     */
    public function incrementVersion()
    {
        $parts = explode('.', $this->version);
        $minor = isset($parts[1]) ? (int)$parts[1] + 1 : 1;
        $this->version = $parts[0] . '.' . $minor;
        $this->last_updated_at = now();
        $this->save();
    }
}
