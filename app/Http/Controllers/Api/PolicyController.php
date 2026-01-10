<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PolicyController extends Controller
{
    /**
     * Get all active policies (public)
     */
    public function index()
    {
        try {
            $policies = Cache::remember('policies_active', 3600, function () {
                return Policy::active()
                    ->select('id', 'title', 'slug', 'type', 'meta_title', 'meta_description', 'last_updated_at', 'version')
                    ->get()
                    ->map(function ($policy) {
                        $policy->type_label = $policy->type_label;
                        return $policy;
                    });
            });

            return response()->json([
                'success' => true,
                'data' => $policies,
                'types' => Policy::TYPES,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching policies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policies',
            ], 500);
        }
    }

    /**
     * Get policy by type (public)
     */
    public function show($type)
    {
        try {
            $policy = Cache::remember("policy_{$type}", 3600, function () use ($type) {
                return Policy::active()->ofType($type)->first();
            });

            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $policy->id,
                    'title' => $policy->title,
                    'slug' => $policy->slug,
                    'type' => $policy->type,
                    'type_label' => $policy->type_label,
                    'content' => $policy->content,
                    'meta_title' => $policy->meta_title,
                    'meta_description' => $policy->meta_description,
                    'last_updated_at' => $policy->last_updated_at,
                    'version' => $policy->version,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching policy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policy',
            ], 500);
        }
    }

    /**
     * Get all policies for admin
     */
    public function adminIndex()
    {
        try {
            $policies = Policy::orderBy('type')->get()->map(function ($policy) {
                $policy->type_label = $policy->type_label;
                return $policy;
            });

            return response()->json([
                'success' => true,
                'data' => $policies,
                'types' => Policy::TYPES,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching policies for admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch policies',
            ], 500);
        }
    }

    /**
     * Store a new policy (admin)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:terms,privacy,refund,shipping|unique:policies,type',
                'content' => 'required|string',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'is_active' => 'boolean',
            ]);

            $validated['slug'] = Str::slug($validated['type']);
            $validated['last_updated_at'] = now();
            $validated['version'] = '1.0';

            $policy = Policy::create($validated);

            // Clear cache
            Cache::forget('policies_active');
            Cache::forget("policy_{$policy->type}");

            return response()->json([
                'success' => true,
                'message' => 'Policy created successfully',
                'data' => $policy,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating policy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create policy',
            ], 500);
        }
    }

    /**
     * Update a policy (admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $policy = Policy::findOrFail($id);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'is_active' => 'boolean',
            ]);

            // Check if content changed to update version
            $contentChanged = $policy->content !== $validated['content'];

            $policy->update($validated);

            if ($contentChanged) {
                $policy->incrementVersion();
            }

            // Clear cache
            Cache::forget('policies_active');
            Cache::forget("policy_{$policy->type}");

            return response()->json([
                'success' => true,
                'message' => 'Policy updated successfully',
                'data' => $policy->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating policy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update policy',
            ], 500);
        }
    }

    /**
     * Delete a policy (admin)
     */
    public function destroy($id)
    {
        try {
            $policy = Policy::findOrFail($id);
            $type = $policy->type;

            $policy->delete();

            // Clear cache
            Cache::forget('policies_active');
            Cache::forget("policy_{$type}");

            return response()->json([
                'success' => true,
                'message' => 'Policy deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting policy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete policy',
            ], 500);
        }
    }

    /**
     * Toggle policy active status (admin)
     */
    public function toggleActive($id)
    {
        try {
            $policy = Policy::findOrFail($id);
            $policy->is_active = !$policy->is_active;
            $policy->save();

            // Clear cache
            Cache::forget('policies_active');
            Cache::forget("policy_{$policy->type}");

            return response()->json([
                'success' => true,
                'message' => 'Policy status updated',
                'data' => $policy,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling policy status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update policy status',
            ], 500);
        }
    }
}
