<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'inquiry_type',
        'order_number',
        'product_name',
        'subject',
        'message',
        'attachment',
        'preferred_contact',
        'status',
        'priority',
        'admin_notes',
        'responded_at',
        'assigned_to',
        'user_id',
        'ip_address',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Inquiry type labels for display
    public static $inquiryTypes = [
        'order_status' => 'Order Status & Tracking',
        'order_issue' => 'Order Issues',
        'product_question' => 'Product Questions',
        'design_help' => 'Design & Artwork Help',
        'bulk_quote' => 'Bulk Order Quote',
        'returns_refunds' => 'Returns & Refunds',
        'print_quality' => 'Print Quality Concern',
        'shipping' => 'Shipping & Delivery',
        'billing' => 'Billing & Invoice',
        'website_issue' => 'Website Technical Issue',
        'partnership' => 'Partnership & Business',
        'other' => 'Other',
    ];

    // Status labels
    public static $statuses = [
        'new' => 'New',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    // Priority labels
    public static $priorities = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('inquiry_type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Accessors
    public function getInquiryTypeLabelAttribute()
    {
        return self::$inquiryTypes[$this->inquiry_type] ?? 'Other';
    }

    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? 'Unknown';
    }

    public function getPriorityLabelAttribute()
    {
        return self::$priorities[$this->priority] ?? 'Normal';
    }

    public function getAttachmentUrlAttribute()
    {
        return $this->attachment ? asset('storage/' . $this->attachment) : null;
    }

    // Check if inquiry is urgent (order-related or high priority)
    public function getIsUrgentAttribute()
    {
        return in_array($this->priority, ['high', 'urgent']) ||
               in_array($this->inquiry_type, ['order_issue', 'print_quality', 'returns_refunds']);
    }
}
