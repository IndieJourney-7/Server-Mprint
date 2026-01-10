<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    /**
     * GET /api/faqs
     * Get all active FAQs for public display (grouped by category)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'faqs_public';

            // Allow cache bypass
            if ($request->has('refresh')) {
                Cache::forget($cacheKey);
            }

            $faqs = Cache::remember($cacheKey, 300, function () {
                return Faq::active()
                    ->ordered()
                    ->get()
                    ->groupBy('category')
                    ->map(function ($items, $category) {
                        return [
                            'category' => $category,
                            'label' => Faq::$categories[$category] ?? 'General',
                            'faqs' => $items->map(function ($faq) {
                                return [
                                    'id' => $faq->id,
                                    'question' => $faq->question,
                                    'answer' => $faq->answer,
                                    'is_featured' => $faq->is_featured,
                                ];
                            })->values()
                        ];
                    })->values();
            });

            return response()->json([
                'success' => true,
                'data' => $faqs,
                'categories' => Faq::$categories
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching FAQs'
            ], 500);
        }
    }

    /**
     * GET /api/faqs/featured
     * Get featured FAQs for homepage display
     */
    public function featured(): JsonResponse
    {
        try {
            $faqs = Cache::remember('faqs_featured', 300, function () {
                return Faq::active()
                    ->featured()
                    ->ordered()
                    ->limit(8)
                    ->get()
                    ->map(function ($faq) {
                        return [
                            'id' => $faq->id,
                            'question' => $faq->question,
                            'answer' => $faq->answer,
                            'category' => $faq->category,
                            'category_label' => $faq->category_label,
                        ];
                    });
            });

            return response()->json([
                'success' => true,
                'data' => $faqs
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ featured error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching featured FAQs'
            ], 500);
        }
    }

    /**
     * POST /api/faqs/{id}/helpful
     * Mark FAQ as helpful or not helpful
     */
    public function markHelpful(Request $request, $id): JsonResponse
    {
        try {
            $faq = Faq::findOrFail($id);
            $isHelpful = $request->input('helpful', true);

            if ($isHelpful) {
                $faq->markHelpful();
            } else {
                $faq->markNotHelpful();
            }

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!',
                'data' => [
                    'helpful_count' => $faq->helpful_count,
                    'not_helpful_count' => $faq->not_helpful_count,
                    'helpful_percentage' => $faq->helpful_percentage
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording feedback'
            ], 500);
        }
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * GET /api/admin/faqs
     * Get all FAQs for admin management
     */
    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $query = Faq::query();

            // Filter by category
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            // Filter by status
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->is_active === 'true');
            }

            // Filter by featured
            if ($request->filled('is_featured')) {
                $query->where('is_featured', $request->is_featured === 'true');
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('question', 'like', "%{$search}%")
                      ->orWhere('answer', 'like', "%{$search}%");
                });
            }

            $faqs = $query->ordered()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $faqs,
                'categories' => Faq::$categories,
                'stats' => [
                    'total' => Faq::count(),
                    'active' => Faq::active()->count(),
                    'featured' => Faq::featured()->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ admin index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching FAQs'
            ], 500);
        }
    }

    /**
     * POST /api/admin/faqs
     * Create a new FAQ
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
            'category' => 'required|string|in:' . implode(',', array_keys(Faq::$categories)),
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $faq = Faq::create([
                'question' => $request->question,
                'answer' => $request->answer,
                'category' => $request->category,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
                'is_featured' => $request->is_featured ?? false,
            ]);

            $this->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'FAQ created successfully',
                'data' => $faq
            ], 201);

        } catch (\Exception $e) {
            Log::error('FAQ store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating FAQ'
            ], 500);
        }
    }

    /**
     * GET /api/admin/faqs/{id}
     * Get single FAQ
     */
    public function show($id): JsonResponse
    {
        try {
            $faq = Faq::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $faq
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found'
            ], 404);
        }
    }

    /**
     * PUT /api/admin/faqs/{id}
     * Update a FAQ
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'sometimes|required|string|max:500',
            'answer' => 'sometimes|required|string|max:5000',
            'category' => 'sometimes|required|string|in:' . implode(',', array_keys(Faq::$categories)),
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $faq = Faq::findOrFail($id);

            $faq->update($request->only([
                'question', 'answer', 'category', 'sort_order', 'is_active', 'is_featured'
            ]));

            $this->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'FAQ updated successfully',
                'data' => $faq->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating FAQ'
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/faqs/{id}
     * Delete a FAQ
     */
    public function destroy($id): JsonResponse
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->delete();

            $this->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting FAQ'
            ], 500);
        }
    }

    /**
     * POST /api/admin/faqs/reorder
     * Reorder FAQs
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'faqs' => 'required|array',
            'faqs.*.id' => 'required|exists:faqs,id',
            'faqs.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->faqs as $item) {
                Faq::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }

            $this->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'FAQs reordered successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ reorder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error reordering FAQs'
            ], 500);
        }
    }

    /**
     * Clear FAQ cache
     */
    private function clearCache(): void
    {
        Cache::forget('faqs_public');
        Cache::forget('faqs_featured');
    }
}
