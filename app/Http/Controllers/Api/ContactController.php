<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    /**
     * POST /api/contacts
     * Submit a new contact form (public endpoint)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'inquiry_type' => 'required|in:order_status,order_issue,product_question,design_help,bulk_quote,returns_refunds,print_quality,shipping,billing,website_issue,partnership,other',
            'order_number' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,doc,docx,ai,psd,eps|max:10240', // 10MB max
            'preferred_contact' => 'nullable|in:email,phone,any',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'name', 'email', 'phone', 'inquiry_type', 'order_number',
                'product_name', 'subject', 'message', 'preferred_contact'
            ]);

            // Handle file upload
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                $path = $file->storeAs('contacts', $filename, 'public');
                $data['attachment'] = $path;
            }

            // Set defaults
            $data['status'] = 'new';
            $data['priority'] = $this->determinePriority($request->inquiry_type);
            $data['ip_address'] = $request->ip();

            // Link to user if authenticated
            if ($request->user()) {
                $data['user_id'] = $request->user()->id;
            }

            $contact = Contact::create($data);

            Log::info('New contact form submission', [
                'contact_id' => $contact->id,
                'inquiry_type' => $contact->inquiry_type,
                'email' => $contact->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting us! We will respond within 1-2 business days.',
                'data' => [
                    'ticket_number' => 'TKT-' . str_pad($contact->id, 6, '0', STR_PAD_LEFT),
                    'created_at' => $contact->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Contact form error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit your message. Please try again.'
            ], 500);
        }
    }

    /**
     * GET /api/admin/contacts
     * Get all contacts for admin (with filters)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Contact::with(['user', 'assignedTo']);

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by inquiry type
            if ($request->filled('inquiry_type')) {
                $query->where('inquiry_type', $request->inquiry_type);
            }

            // Filter by priority
            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            // Filter by read/unread
            if ($request->filled('is_read')) {
                $query->where('is_read', $request->is_read === 'true');
            }

            // Search by name, email, subject, or order number
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('subject', 'like', "%{$search}%")
                      ->orWhere('order_number', 'like', "%{$search}%");
                });
            }

            // Date range filter
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $contacts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'inquiry_types' => Contact::$inquiryTypes,
                'statuses' => Contact::$statuses,
                'priorities' => Contact::$priorities
            ]);

        } catch (\Exception $e) {
            Log::error('Contact index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching contacts'
            ], 500);
        }
    }

    /**
     * GET /api/admin/contacts/{id}
     * Get single contact details
     */
    public function show($id): JsonResponse
    {
        try {
            $contact = Contact::with(['user', 'assignedTo'])->findOrFail($id);

            // Mark as read if not already
            if (!$contact->is_read) {
                $contact->update(['is_read' => true]);
            }

            return response()->json([
                'success' => true,
                'data' => $contact
            ]);

        } catch (\Exception $e) {
            Log::error('Contact show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Contact not found'
            ], 404);
        }
    }

    /**
     * PUT /api/admin/contacts/{id}
     * Update contact status/notes
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:new,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'admin_notes' => 'nullable|string|max:5000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = Contact::findOrFail($id);

            $updateData = $request->only(['status', 'priority', 'admin_notes', 'assigned_to']);

            // Set responded_at when status changes from 'new'
            if ($request->has('status') && $contact->status === 'new' && $request->status !== 'new') {
                $updateData['responded_at'] = now();
            }

            $contact->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully',
                'data' => $contact->fresh(['user', 'assignedTo'])
            ]);

        } catch (\Exception $e) {
            Log::error('Contact update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating contact'
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/contacts/{id}
     * Delete a contact
     */
    public function destroy($id): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($id);

            // Delete attachment if exists
            if ($contact->attachment) {
                Storage::disk('public')->delete($contact->attachment);
            }

            $contact->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Contact delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting contact'
            ], 500);
        }
    }

    /**
     * GET /api/admin/contacts/stats
     * Get contact statistics for dashboard
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Contact::count(),
                'new' => Contact::where('status', 'new')->count(),
                'in_progress' => Contact::where('status', 'in_progress')->count(),
                'resolved' => Contact::where('status', 'resolved')->count(),
                'unread' => Contact::where('is_read', false)->count(),
                'urgent' => Contact::whereIn('priority', ['high', 'urgent'])->where('status', '!=', 'closed')->count(),
                'today' => Contact::whereDate('created_at', today())->count(),
                'this_week' => Contact::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'by_type' => Contact::selectRaw('inquiry_type, count(*) as count')
                    ->groupBy('inquiry_type')
                    ->pluck('count', 'inquiry_type'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Contact stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics'
            ], 500);
        }
    }

    /**
     * POST /api/admin/contacts/{id}/mark-read
     * Mark contact as read
     */
    public function markRead($id): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($id);
            $contact->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating contact'
            ], 500);
        }
    }

    /**
     * Determine priority based on inquiry type
     */
    private function determinePriority(string $inquiryType): string
    {
        $highPriority = ['order_issue', 'print_quality', 'returns_refunds'];
        $normalPriority = ['order_status', 'shipping', 'billing'];

        if (in_array($inquiryType, $highPriority)) {
            return 'high';
        }

        return 'normal';
    }
}
