<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subcategory;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubcategoryController extends Controller
{
    /**
     * GET /api/subcategories
     * Get all subcategories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Subcategory::with('category');

            if ($request->filled('active_only') && $request->active_only) {
                $query->active();
            }

            $subcategories = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $subcategories
            ]);
        } catch (\Exception $e) {
            Log::error('Subcategory index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subcategories'
            ], 500);
        }
    }

    /**
     * GET /api/subcategories/category/{categoryId}
     * Get subcategories for a specific category
     */
    public function byCategory($categoryId): JsonResponse
    {
        try {
            $subcategories = Subcategory::where('category_id', $categoryId)
                ->active()
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $subcategories
            ]);
        } catch (\Exception $e) {
            Log::error('Subcategory byCategory error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subcategories'
            ], 500);
        }
    }

    /**
     * GET /api/subcategories/{id}
     * Get a specific subcategory
     */
    public function show($id): JsonResponse
    {
        try {
            $subcategory = Subcategory::with('category')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $subcategory
            ]);
        } catch (\Exception $e) {
            Log::error('Subcategory show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Subcategory not found'
            ], 404);
        }
    }

    /**
     * POST /api/subcategories
     * Create a new subcategory
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:subcategories,slug',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $slug = $request->slug ?? Str::slug($request->name);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (Subcategory::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $subcategory = Subcategory::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
            ]);

            // Clear mega menu cache
            MegaMenuController::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Subcategory created successfully',
                'data' => $subcategory
            ], 201);
        } catch (\Exception $e) {
            Log::error('Subcategory store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating subcategory'
            ], 500);
        }
    }

    /**
     * PUT /api/subcategories/{id}
     * Update a subcategory
     */
    public function update(Request $request, $id): JsonResponse
    {
        $subcategory = Subcategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:subcategories,slug,' . $id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];

            if ($request->has('category_id')) {
                $updateData['category_id'] = $request->category_id;
            }
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('slug')) {
                $updateData['slug'] = $request->slug;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }
            if ($request->has('sort_order')) {
                $updateData['sort_order'] = $request->sort_order;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            $subcategory->update($updateData);

            // Clear mega menu cache
            MegaMenuController::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Subcategory updated successfully',
                'data' => $subcategory->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Subcategory update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating subcategory'
            ], 500);
        }
    }

    /**
     * DELETE /api/subcategories/{id}
     * Delete a subcategory
     */
    public function destroy($id): JsonResponse
    {
        try {
            $subcategory = Subcategory::findOrFail($id);
            $subcategory->delete();

            // Clear mega menu cache
            MegaMenuController::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Subcategory deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Subcategory delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting subcategory'
            ], 500);
        }
    }
}
