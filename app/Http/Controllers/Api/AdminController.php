<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Complaint;
use App\Models\UserUpload;
use App\Models\UserDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /**
     * Get all orders for admin panel
     */
    public function getOrders(Request $request)
    {
        try {
            $query = Order::with(['user', 'orderItems.product', 'orderItems.design']);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all complaints/tickets for admin panel
     */
    public function getComplaints(Request $request)
    {
        try {
            $query = Complaint::with(['user', 'order', 'product']);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('issue_type')) {
                $query->where('issue_type', $request->issue_type);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $complaints = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $complaints
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch complaints',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update complaint status and add admin response
     */
    public function updateComplaintStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_review,resolved,rejected',
            'admin_response' => 'nullable|string',
        ]);

        try {
            $complaint = Complaint::findOrFail($id);

            $complaint->update([
                'status' => $validated['status'],
                'admin_response' => $validated['admin_response'] ?? $complaint->admin_response,
                'resolved_at' => in_array($validated['status'], ['resolved', 'rejected'])
                    ? now()
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Complaint status updated successfully',
                'data' => $complaint->load(['user', 'order', 'product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
        ]);

        try {
            $order = Order::findOrFail($id);

            $order->update([
                'status' => $validated['status'],
                'payment_status' => $validated['payment_status'] ?? $order->payment_status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order->load(['user', 'orderItems.product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $stats = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'delivered_orders' => Order::where('status', 'delivered')->count(),
                'total_complaints' => Complaint::count(),
                'pending_complaints' => Complaint::where('status', 'pending')->count(),
                'resolved_complaints' => Complaint::where('status', 'resolved')->count(),
                'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
                'pending_revenue' => Order::where('payment_status', 'pending')->sum('total'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all user uploads for admin panel
     */
    public function getUserUploads(Request $request)
    {
        try {
            $query = UserUpload::with('user:id,name,email');

            // Filter by user
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Search by filename
            if ($request->filled('search')) {
                $query->where('original_name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $uploads = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $uploads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user uploads',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all user designs for admin panel
     */
    public function getUserDesigns(Request $request)
    {
        try {
            $query = UserDesign::with(['user:id,name,email', 'product:id,name,slug']);

            // Filter by user
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Search by name
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $designs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $designs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user designs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user upload (admin action)
     */
    public function deleteUserUpload($id)
    {
        try {
            $upload = UserUpload::findOrFail($id);
            
            // Delete files
            if ($upload->file_path) {
                Storage::disk('public')->delete($upload->file_path);
            }
            if ($upload->thumbnail_path) {
                Storage::disk('public')->delete($upload->thumbnail_path);
            }
            
            $upload->delete();

            return response()->json([
                'success' => true,
                'message' => 'Upload deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upload statistics for dashboard
     */
    public function getUploadStats()
    {
        try {
            $stats = [
                'total_uploads' => UserUpload::count(),
                'total_designs' => UserDesign::count(),
                'draft_designs' => UserDesign::where('status', 'draft')->count(),
                'completed_designs' => UserDesign::where('status', 'completed')->count(),
                'total_storage_bytes' => UserUpload::sum('file_size'),
                'uploads_today' => UserUpload::whereDate('created_at', today())->count(),
                'designs_today' => UserDesign::whereDate('created_at', today())->count(),
            ];

            // Format storage size
            $bytes = $stats['total_storage_bytes'];
            if ($bytes >= 1073741824) {
                $stats['total_storage'] = round($bytes / 1073741824, 2) . ' GB';
            } elseif ($bytes >= 1048576) {
                $stats['total_storage'] = round($bytes / 1048576, 2) . ' MB';
            } else {
                $stats['total_storage'] = round($bytes / 1024, 2) . ' KB';
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upload statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
