<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MegaMenuController extends Controller
{
    /**
     * GET /api/mega-menu
     * Get categories with their subcategories and limited products for mega dropdown
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Cache for 5 minutes to improve performance
            $cacheKey = 'mega_menu_data';

            $menuData = Cache::remember($cacheKey, 300, function () {
                return $this->buildMenuData();
            });

            return response()->json([
                'success' => true,
                'data' => $menuData
            ]);
        } catch (\Exception $e) {
            Log::error('MegaMenu index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching menu data'
            ], 500);
        }
    }

    /**
     * GET /api/mega-menu/{categorySlug}
     * Get specific category data for mega dropdown
     */
    public function show(Request $request, string $categorySlug): JsonResponse
    {
        try {
            $cacheKey = 'mega_menu_' . $categorySlug;

            // Allow cache bypass with ?refresh=1
            if ($request->has('refresh')) {
                Cache::forget($cacheKey);
            }

            $categoryData = Cache::remember($cacheKey, 300, function () use ($categorySlug) {
                return $this->buildCategoryMenuData($categorySlug);
            });

            if (!$categoryData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $categoryData
            ]);
        } catch (\Exception $e) {
            Log::error('MegaMenu show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching category menu data'
            ], 500);
        }
    }

    /**
     * Build complete menu data for all categories
     */
    private function buildMenuData(): array
    {
        $categories = Category::with(['subcategories' => function ($query) {
            $query->active()->ordered();
        }])->active()->ordered()->get();

        $menuData = [];

        foreach ($categories as $category) {
            $menuData[] = $this->formatCategoryData($category);
        }

        return $menuData;
    }

    /**
     * Build menu data for a specific category
     */
    private function buildCategoryMenuData(string $categorySlug): ?array
    {
        $category = Category::with(['subcategories' => function ($query) {
            $query->active()->ordered();
        }])->where('slug', $categorySlug)->active()->first();

        if (!$category) {
            return null;
        }

        // Special handling for Bulk Orders - show all products from all categories
        if ($categorySlug === 'bulk-orders') {
            return $this->buildBulkOrdersMenuData($category);
        }

        return $this->formatCategoryData($category);
    }

    /**
     * Build special menu data for Bulk Orders
     * Shows all products grouped by their original categories
     */
    private function buildBulkOrdersMenuData(Category $bulkOrdersCategory): array
    {
        $columns = [];
        $maxProductsPerColumn = 6;

        // Get all categories except Bulk Orders
        $categories = Category::where('slug', '!=', 'bulk-orders')
            ->active()
            ->ordered()
            ->get();

        foreach ($categories as $category) {
            // Get products from this category
            $products = Product::where('category_id', $category->id)
                ->active()
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($maxProductsPerColumn)
                ->get();

            if ($products->count() === 0) {
                continue; // Skip categories with no products
            }

            $totalProducts = Product::where('category_id', $category->id)
                ->active()
                ->count();

            $columns[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'products' => $products->map(function ($product) use ($category) {
                    return $this->formatProductData($product, $category);
                }),
                'total_count' => $totalProducts,
                'has_more' => $totalProducts > $maxProductsPerColumn,
                'see_all_link' => '/' . $category->slug
            ];
        }

        return [
            'id' => $bulkOrdersCategory->id,
            'name' => $bulkOrdersCategory->name,
            'slug' => $bulkOrdersCategory->slug,
            'description' => $bulkOrdersCategory->description,
            'image_url' => $bulkOrdersCategory->image ? asset('storage/' . $bulkOrdersCategory->image) : null,
            'columns' => $columns,
            'see_all_link' => '/bulk-orders'
        ];
    }

    /**
     * Format category data with subcategories and products
     */
    private function formatCategoryData(Category $category): array
    {
        $columns = [];
        $maxProductsPerColumn = 8;

        // If category has subcategories, organize products by subcategory
        if ($category->subcategories->count() > 0) {
            foreach ($category->subcategories as $subcategory) {
                $products = Product::where('category_id', $category->id)
                    ->where('subcategory_id', $subcategory->id)
                    ->active()
                    ->orderBy('is_featured', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->limit($maxProductsPerColumn)
                    ->get();

                $totalProducts = Product::where('category_id', $category->id)
                    ->where('subcategory_id', $subcategory->id)
                    ->active()
                    ->count();

                $columns[] = [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'slug' => $subcategory->slug,
                    'products' => $products->map(function ($product) use ($category) {
                        return $this->formatProductData($product, $category);
                    }),
                    'total_count' => $totalProducts,
                    'has_more' => $totalProducts > $maxProductsPerColumn,
                    'see_all_link' => '/' . $category->slug . '?subcategory=' . $subcategory->slug
                ];
            }
        }

        // Also get products without subcategory (ungrouped)
        $ungroupedProducts = Product::where('category_id', $category->id)
            ->whereNull('subcategory_id')
            ->active()
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($maxProductsPerColumn)
            ->get();

        $totalUngrouped = Product::where('category_id', $category->id)
            ->whereNull('subcategory_id')
            ->active()
            ->count();

        // If no subcategories exist, put all products in a default column
        if ($category->subcategories->count() === 0 && $ungroupedProducts->count() > 0) {
            $columns[] = [
                'id' => 0,
                'name' => 'All ' . $category->name,
                'slug' => 'all',
                'products' => $ungroupedProducts->map(function ($product) use ($category) {
                    return $this->formatProductData($product, $category);
                }),
                'total_count' => $totalUngrouped,
                'has_more' => $totalUngrouped > $maxProductsPerColumn,
                'see_all_link' => '/' . $category->slug
            ];
        } elseif ($ungroupedProducts->count() > 0) {
            // Add ungrouped products as a separate column
            $columns[] = [
                'id' => 0,
                'name' => 'Other',
                'slug' => 'other',
                'products' => $ungroupedProducts->map(function ($product) use ($category) {
                    return $this->formatProductData($product, $category);
                }),
                'total_count' => $totalUngrouped,
                'has_more' => $totalUngrouped > $maxProductsPerColumn,
                'see_all_link' => '/' . $category->slug
            ];
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image ? asset('storage/' . $category->image) : null,
            'columns' => $columns,
            'see_all_link' => '/' . $category->slug
        ];
    }

    /**
     * Format product data for menu display
     */
    private function formatProductData(Product $product, Category $category): array
    {
        // Calculate if product should show NEW badge
        $showNewBadge = false;

        // Manual flag takes priority
        if ($product->is_new) {
            $showNewBadge = true;
        } else {
            // Auto-new based on creation date (default 30 days)
            $daysThreshold = $product->new_until_days ?? 30;
            if ($product->created_at) {
                $daysSinceCreation = $product->created_at->diffInDays(now());
                $showNewBadge = $daysSinceCreation <= $daysThreshold;
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'current_price' => $product->sale_price ?? $product->price,
            'is_new' => $showNewBadge,
            'is_featured' => $product->is_featured,
            'link' => '/' . $category->slug . '/' . $product->slug,
            'featured_image_url' => $product->featured_image
                ? asset('storage/' . $product->featured_image)
                : null
        ];
    }

    /**
     * Clear mega menu cache
     * Call this when categories, subcategories, or products are updated
     */
    public static function clearCache(?string $categorySlug = null): void
    {
        if ($categorySlug) {
            Cache::forget('mega_menu_' . $categorySlug);
        }
        Cache::forget('mega_menu_data');
    }
}
