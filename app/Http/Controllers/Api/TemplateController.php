<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\TemplateColorVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    /**
     * Get templates with filters
     */
    public function index(Request $request)
    {
        try {
            $query = Template::with(['colorVariants', 'category', 'subcategory'])
                ->active()
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('category_id')) {
                $query->byCategory($request->category_id);
            }

            if ($request->has('orientation')) {
                $query->byOrientation($request->orientation);
            }

            if ($request->has('corners')) {
                $query->byCorners($request->corners);
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('featured')) {
                $query->featured();
            }

            // Pagination
            $perPage = $request->get('per_page', 12);
            $templates = $query->paginate($perPage);

            // Add is_favorited flag for authenticated users
            if (Auth::check()) {
                $userId = Auth::id();
                $templates->getCollection()->transform(function ($template) use ($userId) {
                    $template->is_favorited = $template->isFavoritedBy($userId);
                    return $template;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates',
            ], 500);
        }
    }

    /**
     * Get single template with all details
     */
    public function show($id)
    {
        try {
            $template = Template::with(['colorVariants', 'category', 'subcategory'])
                ->active()
                ->findOrFail($id);

            // Add is_favorited flag for authenticated users
            if (Auth::check()) {
                $template->is_favorited = $template->isFavoritedBy(Auth::id());
            }

            return response()->json([
                'success' => true,
                'data' => $template,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }
    }

    /**
     * Toggle favorite status
     */
    public function toggleFavorite(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $template = Template::findOrFail($id);

            if ($template->isFavoritedBy($user->id)) {
                // Remove from favorites
                $user->favoriteTemplates()->detach($id);
                $isFavorited = false;
            } else {
                // Add to favorites
                $user->favoriteTemplates()->attach($id);
                $isFavorited = true;
            }

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited,
                'message' => $isFavorited ? 'Added to favorites' : 'Removed from favorites',
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling favorite: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update favorite status',
            ], 500);
        }
    }

    /**
     * Get user's favorite templates
     */
    public function getFavorites(Request $request)
    {
        try {
            $user = Auth::user();
            $templates = $user->favoriteTemplates()
                ->with(['colorVariants', 'category'])
                ->paginate($request->get('per_page', 12));

            $templates->getCollection()->transform(function ($template) {
                $template->is_favorited = true;
                return $template;
            });

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching favorites: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch favorites',
            ], 500);
        }
    }

    /**
     * Use template - increment usage and return template data
     */
    public function useTemplate($id)
    {
        try {
            $template = Template::with('colorVariants')->findOrFail($id);
            $template->incrementUsage();

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template ready for customization',
            ]);
        } catch (\Exception $e) {
            Log::error('Error using template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load template',
            ], 500);
        }
    }

    /**
     * Get color variant details
     */
    public function getColorVariant($templateId, $variantId)
    {
        try {
            $variant = TemplateColorVariant::where('template_id', $templateId)
                ->findOrFail($variantId);

            return response()->json([
                'success' => true,
                'data' => $variant,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching color variant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Color variant not found',
            ], 404);
        }
    }
}
