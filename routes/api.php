<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\OfferBarController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MegaMenuController;
use App\Http\Controllers\Api\SubcategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PolicyController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Api\DesignController;
use App\Http\Controllers\Api\UserUploadController;
use App\Http\Controllers\Api\WorkSessionController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\AdminTemplateController;

Route::get('/health', fn() => response()->json([
  'success' => true,
  'message' => 'API is running',
  'timestamp' => now()->toIso8601String()
]));

// Public: categories/products
Route::prefix('categories')->group(function () {
  Route::get('/', [CategoryController::class, 'index']);
  Route::get('/featured', [CategoryController::class, 'featured']);
  Route::get('/{slug}', [CategoryController::class, 'show']);
  Route::post('/', [CategoryController::class, 'store']);
  Route::put('/{id}', [CategoryController::class, 'update']);
  Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

Route::prefix('products')->group(function () {
  Route::get('/', [ProductController::class, 'index']);
  Route::get('/featured', [ProductController::class, 'featured']);
  Route::get('/new-arrivals', [ProductController::class, 'newArrivals']);
  Route::get('/trending', [ProductController::class, 'trending']);
  Route::get('/branded', [ProductController::class, 'branded']);
  Route::get('/category/{categorySlug}', [ProductController::class, 'byCategory']);
  Route::get('/{slug}', [ProductController::class, 'show']);
  Route::post('/', [ProductController::class, 'store']);
  Route::put('/{id}', [ProductController::class, 'update']);
  Route::delete('/{id}', [ProductController::class, 'destroy']);
  Route::post('/{id}/increment-views', [ProductController::class, 'incrementViews']);
  Route::get('/{slug}/related', [ProductController::class, 'relatedProducts']);
});

// Public: templates (browse designs)
Route::prefix('templates')->group(function () {
  Route::get('/', [TemplateController::class, 'index']); // List all templates with filters
  Route::get('/{id}', [TemplateController::class, 'show']); // Get single template
  Route::post('/{id}/use', [TemplateController::class, 'useTemplate']); // Use template (increment usage)
  Route::get('/{templateId}/variants/{variantId}', [TemplateController::class, 'getColorVariant']); // Get color variant

  // Protected routes - require authentication
  Route::middleware('auth:sanctum')->group(function () {
    Route::post('/{id}/favorite', [TemplateController::class, 'toggleFavorite']); // Toggle favorite
    Route::get('/favorites/list', [TemplateController::class, 'getFavorites']); // Get user favorites
  });
});

// Public: banners & offer bars
Route::prefix('banners')->group(function () {
  Route::get('/', [BannerController::class, 'index']);
  Route::post('/', [BannerController::class, 'store']);
  Route::put('/{id}', [BannerController::class, 'update']);
  Route::delete('/{id}', [BannerController::class, 'destroy']);
});

Route::prefix('offer-bars')->group(function () {
  Route::get('/', [OfferBarController::class, 'index']);
  Route::post('/', [OfferBarController::class, 'store']);
  Route::put('/{id}', [OfferBarController::class, 'update']);
  Route::delete('/{id}', [OfferBarController::class, 'destroy']);
});

// Public: mega menu for dropdown navigation
Route::prefix('mega-menu')->group(function () {
  Route::get('/', [MegaMenuController::class, 'index']);
  Route::get('/{categorySlug}', [MegaMenuController::class, 'show']);
});

// Public: subcategories
Route::prefix('subcategories')->group(function () {
  Route::get('/', [SubcategoryController::class, 'index']);
  Route::get('/category/{categoryId}', [SubcategoryController::class, 'byCategory']);
  Route::get('/{id}', [SubcategoryController::class, 'show']);
  Route::post('/', [SubcategoryController::class, 'store']);
  Route::put('/{id}', [SubcategoryController::class, 'update']);
  Route::delete('/{id}', [SubcategoryController::class, 'destroy']);
});

// Public: contact form submission
Route::post('/contacts', [ContactController::class, 'store']);

// Public: FAQs
Route::prefix('faqs')->group(function () {
  Route::get('/', [FaqController::class, 'index']);
  Route::get('/featured', [FaqController::class, 'featured']);
  Route::post('/{id}/helpful', [FaqController::class, 'markHelpful']);
});

// Public: Policies (Terms, Privacy, Refund, Shipping)
Route::prefix('policies')->group(function () {
  Route::get('/', [PolicyController::class, 'index']);
  Route::get('/{type}', [PolicyController::class, 'show']);
});

// Public: auth
Route::prefix('auth')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  Route::post('/admin/login', [AuthController::class, 'adminLogin']);
});

// Admin routes protected by JWT
Route::prefix('admin')->middleware('admin.jwt')->group(function () {
  Route::get('/verify', [AuthController::class, 'verifyAdmin']);
  Route::post('/logout', [AuthController::class, 'adminLogout']);
});

// Protected: user + cart + favorites (login required)
Route::middleware('auth:sanctum')->group(function () {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/profile', [AuthController::class, 'profile']);
  Route::get('/user', fn(\Illuminate\Http\Request $r) => response()->json(['success'=>true,'user'=>$r->user()]));

  Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/add', [CartController::class, 'addToCart']);
    Route::put('/update/{id}', [CartController::class, 'updateQuantity']);
    Route::delete('/remove/{id}', [CartController::class, 'removeFromCart']);
    Route::delete('/clear', [CartController::class, 'clearCart']);
    Route::get('/count', [CartController::class, 'getCartCount']);
    Route::get('/total', [CartController::class, 'getCartTotal']);
    Route::post('/{cartId}/upload-designs', [CartController::class, 'uploadDesigns']);
    Route::delete('/{cartId}/designs/{side}', [CartController::class, 'deleteDesign']);
  });

  Route::prefix('favorites')->group(function () {
    Route::get('/', [FavoriteController::class, 'index']);
    Route::post('/add', [FavoriteController::class, 'add']);
    Route::post('/toggle', [FavoriteController::class, 'toggle']);
    Route::delete('/remove/{productId}', [FavoriteController::class, 'remove']);
    Route::get('/check/{productId}', [FavoriteController::class, 'check']);
    Route::get('/count', [FavoriteController::class, 'count']);
    Route::delete('/clear', [FavoriteController::class, 'clear']);
  });

  Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{id}', [OrderController::class, 'show']);
    Route::post('/raise-ticket', [OrderController::class, 'raiseTicket']);
  });

  Route::prefix('complaints')->group(function () {
    Route::get('/', [OrderController::class, 'getUserComplaints']);
  });

  // Work Sessions (Persistent Design State)
  Route::prefix('work-sessions')->group(function () {
    Route::post('/init', [WorkSessionController::class, 'initOrRestore']);
    Route::post('/save', [WorkSessionController::class, 'save']);
    Route::get('/{sessionId}', [WorkSessionController::class, 'get']);
    Route::post('/link-design', [WorkSessionController::class, 'linkDesign']);
  });

  // User Designs (My Projects)
  Route::prefix('designs')->group(function () {
    Route::get('/', [DesignController::class, 'index']); // List all designs
    Route::post('/', [DesignController::class, 'store']); // Create new design
    Route::get('/{id}', [DesignController::class, 'show']); // Get single design
    Route::get('/{id}/edit', [DesignController::class, 'getForEditing']); // Get design with canvas state for editing
    Route::put('/{id}', [DesignController::class, 'update']); // Update design details
    Route::delete('/{id}', [DesignController::class, 'destroy']); // Delete design
    Route::post('/{id}/upload', [DesignController::class, 'uploadImage']); // Upload front/back image
    Route::post('/{id}/copy-from-upload', [DesignController::class, 'copyFromUpload']); // Copy from user's upload library
    Route::put('/{id}/position', [DesignController::class, 'updatePosition']); // Update position data
    Route::post('/{id}/canvas-state', [DesignController::class, 'saveCanvasState']); // Save canvas state for resuming
    Route::post('/{id}/finalize', [DesignController::class, 'finalize']); // Mark as completed
    Route::post('/{id}/final-images', [DesignController::class, 'saveFinalImages']); // Save final images for ordering
    Route::post('/{id}/duplicate', [DesignController::class, 'duplicate']); // Duplicate design
  });

  // User Uploads (Reusable image library - like Canva's Uploads panel)
  Route::prefix('uploads')->group(function () {
    Route::get('/', [UserUploadController::class, 'index']); // List all uploads with pagination
    Route::get('/recent', [UserUploadController::class, 'recent']); // Get recent uploads (quick access)
    Route::post('/', [UserUploadController::class, 'store']); // Upload new image
    Route::post('/{id}/used', [UserUploadController::class, 'markUsed']); // Mark as used
    Route::post('/{id}/favorite', [UserUploadController::class, 'toggleFavorite']); // Toggle favorite
    Route::delete('/{id}', [UserUploadController::class, 'destroy']); // Delete single upload
    Route::post('/bulk-delete', [UserUploadController::class, 'bulkDestroy']); // Bulk delete
  });

  // Admin routes (TODO: Add admin authentication middleware)
  Route::prefix('admin')->group(function () {
    Route::get('/orders', [AdminController::class, 'getOrders']);
    Route::get('/complaints', [AdminController::class, 'getComplaints']);
    Route::get('/stats', [AdminController::class, 'getDashboardStats']);
    Route::put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
    Route::put('/complaints/{id}/status', [AdminController::class, 'updateComplaintStatus']);
    
    // User uploads & designs management
    Route::get('/uploads', [AdminController::class, 'getUserUploads']);
    Route::get('/uploads/stats', [AdminController::class, 'getUploadStats']);
    Route::delete('/uploads/{id}', [AdminController::class, 'deleteUserUpload']);
    Route::get('/designs', [AdminController::class, 'getUserDesigns']);

    // Contact management
    Route::prefix('contacts')->group(function () {
      Route::get('/', [ContactController::class, 'index']);
      Route::get('/stats', [ContactController::class, 'stats']);
      Route::get('/{id}', [ContactController::class, 'show']);
      Route::put('/{id}', [ContactController::class, 'update']);
      Route::delete('/{id}', [ContactController::class, 'destroy']);
      Route::post('/{id}/mark-read', [ContactController::class, 'markRead']);
    });

    // FAQ management
    Route::prefix('faqs')->group(function () {
      Route::get('/', [FaqController::class, 'adminIndex']);
      Route::post('/', [FaqController::class, 'store']);
      Route::get('/{id}', [FaqController::class, 'show']);
      Route::put('/{id}', [FaqController::class, 'update']);
      Route::delete('/{id}', [FaqController::class, 'destroy']);
      Route::post('/reorder', [FaqController::class, 'reorder']);
    });

    // Policy management
    Route::prefix('policies')->group(function () {
      Route::get('/', [PolicyController::class, 'adminIndex']);
      Route::post('/', [PolicyController::class, 'store']);
      Route::put('/{id}', [PolicyController::class, 'update']);
      Route::delete('/{id}', [PolicyController::class, 'destroy']);
      Route::post('/{id}/toggle-active', [PolicyController::class, 'toggleActive']);
    });

    // Template management
    Route::prefix('templates')->group(function () {
      Route::get('/', [AdminTemplateController::class, 'index']);
      Route::post('/', [AdminTemplateController::class, 'store']);
      Route::put('/{id}', [AdminTemplateController::class, 'update']);
      Route::delete('/{id}', [AdminTemplateController::class, 'destroy']);

      // Color variant management
      Route::post('/{templateId}/variants', [AdminTemplateController::class, 'addColorVariant']);
      Route::put('/{templateId}/variants/{variantId}', [AdminTemplateController::class, 'updateColorVariant']);
      Route::delete('/{templateId}/variants/{variantId}', [AdminTemplateController::class, 'deleteColorVariant']);
    });
  });
});

// Serve storage files with CORS headers for canvas export
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!file_exists($filePath)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    $mimeType = mime_content_type($filePath);

    // Get the request origin for CORS
    $origin = request()->header('Origin', 'http://localhost:3000');

    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        // Use specific origin for CORS with credentials support
        'Access-Control-Allow-Origin' => $origin,
        'Access-Control-Allow-Credentials' => 'true',
        // Required for canvas.toDataURL() to work with images loaded with crossOrigin='anonymous'
        'Cross-Origin-Resource-Policy' => 'cross-origin',
    ]);
})->where('path', '.*');

// Handle OPTIONS preflight for storage files
Route::options('/storage/{path}', function ($path) {
    $origin = request()->header('Origin', 'http://localhost:3000');

    return response('', 200, [
        'Access-Control-Allow-Origin' => $origin,
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Max-Age' => '86400',
    ]);
})->where('path', '.*');
