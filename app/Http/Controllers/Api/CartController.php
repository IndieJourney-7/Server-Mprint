<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class CartController extends Controller
{
    private function normalizeAttributes(array $attrs): array
    {
        ksort($attrs);
        foreach ($attrs as $k => $v) {
            if (is_array($v)) $attrs[$k] = $this->normalizeAttributes($v);
        }
        return $attrs;
    }

    // GET /api/cart (auth required)
    public function index(Request $request)
    {
        try {
            $userId = auth()->id();

            $items = Cart::with(['product.category','product.images', 'design.template'])
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->get()
                ->filter(fn($i) => $i->product !== null);

            $formatted = $items->map(fn($item) => [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'price' => $item->product->price,
                    'featured_image_url' => $item->product->featured_image_url ?? '/placeholder.png',
                    'category' => optional($item->product->category)->name ?? 'No Category',
                    'attributes' => $item->product->attributes ?? [],
                ],
                'quantity' => $item->quantity,
                'selected_attributes' => $item->selected_attributes ?? [],
                // Design info for displaying user's custom designs
                'design_id' => $item->design_id,
                'front_design_url' => $item->front_design_url,
                'back_design_url' => $item->back_design_url,
                // Include original high-res URLs if design exists
                'front_original_url' => $item->design?->front_original_url,
                'back_original_url' => $item->design?->back_original_url,
                // Design type info - helps Cart render correctly
                'design_type' => $item->design?->design_type ?? 'blank',
                'template_id' => $item->design?->template_id,
                'template_name' => $item->design?->template?->name,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]);

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => $items->count(),
                'total' => $items->sum('total_price'),
            ]);
        } catch (\Exception $e) {
            Log::error('Cart#index failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch cart'], 500);
        }
    }

    // POST /api/cart/add (auth required)
    public function addToCart(Request $request)
    {
        Log::info('[CartController] addToCart called with:', [
            'product_id' => $request->input('product_id'),
            'quantity' => $request->input('quantity'),
            'design_id' => $request->input('design_id'),
            'has_design_id' => $request->has('design_id'),
            'all_input' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'selected_attributes' => 'nullable|array',
            'design_id' => 'nullable|exists:user_designs,id'
        ]);
        if ($validator->fails()) {
            Log::warning('[CartController] Validation failed:', $validator->errors()->toArray());
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $userId = auth()->id();
            $product = Product::findOrFail($request->product_id);

            $unitPrice = $product->price;
            $qty = (int) $request->quantity;
            $attrs = $this->normalizeAttributes($request->input('selected_attributes', []));
            $attrsJson = json_encode($attrs);
            $designId = $request->input('design_id');

            // If design_id is provided, check that it belongs to the user
            if ($designId) {
                $design = \App\Models\UserDesign::where('id', $designId)
                    ->where('user_id', $userId)
                    ->first();
                if (!$design) {
                    Log::warning('[CartController] Design not found or does not belong to user', [
                        'design_id' => $designId,
                        'user_id' => $userId,
                    ]);
                    $designId = null; // Invalid design, ignore it
                } else {
                    Log::info('[CartController] Design validated successfully', [
                        'design_id' => $designId,
                        'design_has_front_preview' => !empty($design->front_preview_path),
                        'design_has_back_preview' => !empty($design->back_preview_path),
                    ]);
                }
            } else {
                Log::info('[CartController] No design_id provided in request');
            }

            $existing = Cart::where('user_id', $userId)
                ->where('product_id', $request->product_id)
                ->where('selected_attributes', $attrsJson)
                ->first();

            if ($existing) {
                DB::transaction(function () use ($existing, $qty, $unitPrice, $designId) {
                    $existing->quantity += $qty;
                    $existing->unit_price = $unitPrice;
                    $existing->total_price = $existing->unit_price * $existing->quantity;
                    // Update design_id if provided
                    if ($designId) {
                        $existing->design_id = $designId;
                    }
                    $existing->save();
                });
                return response()->json(['success' => true, 'message' => 'Cart updated', 'data' => $existing]);
            }

            $cartItem = Cart::create([
                'user_id' => $userId,
                'session_id' => null,
                'product_id' => $request->product_id,
                'design_id' => $designId,
                'quantity' => $qty,
                'selected_attributes' => $attrs,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $qty,
            ]);

            return response()->json(['success' => true, 'message' => 'Added to cart', 'data' => $cartItem], 201);
        } catch (\Exception $e) {
            Log::error('Cart#add failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to add to cart'], 500);
        }
    }

    // PUT /api/cart/update/{id} (auth required)
    // Now also supports updating design_id for Edit Design from Cart flow
    public function updateQuantity(Request $request, $cartId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'selected_attributes' => 'nullable|array',
            'design_id' => 'nullable|exists:user_designs,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $userId = auth()->id();
            $cart = Cart::with('product')->where('user_id', $userId)->findOrFail($cartId);

            $newQuantity = (int) $request->quantity;

            // Update quantity
            $cart->quantity = $newQuantity;

            // Update design_id if provided (for Edit Design from Cart flow)
            if ($request->has('design_id')) {
                $designId = $request->input('design_id');
                // Verify design belongs to user
                if ($designId) {
                    $design = \App\Models\UserDesign::where('id', $designId)
                        ->where('user_id', $userId)
                        ->first();
                    if ($design) {
                        $cart->design_id = $designId;
                        Log::info('[updateQuantity] Design ID updated', [
                            'cart_id' => $cartId,
                            'design_id' => $designId,
                        ]);
                    }
                }
            }

            // Update selected_attributes if provided
            if ($request->has('selected_attributes')) {
                $attrs = $this->normalizeAttributes($request->input('selected_attributes', []));
                $cart->selected_attributes = $attrs;
            }
            
            // Recalculate pricing based on product's pricing tiers if available
            $unitPrice = $cart->unit_price; // Default to existing unit price
            
            if ($cart->product && $cart->product->attributes) {
                $productAttrs = $cart->product->attributes;
                // Handle string or array
                if (is_string($productAttrs)) {
                    $productAttrs = json_decode($productAttrs, true) ?? [];
                }
                if (!is_array($productAttrs)) {
                    $productAttrs = [];
                }
                    
                // Look for pricing_tiers in product attributes
                $pricingTiers = null;
                foreach ($productAttrs as $key => $value) {
                    $normalizedKey = strtolower(str_replace(['_', ' '], '', $key));
                    if ($normalizedKey === 'pricingtiers' && is_array($value)) {
                        $pricingTiers = $value;
                        break;
                    }
                }
                
                if ($pricingTiers) {
                    // Find the matching tier for the quantity
                    foreach ($pricingTiers as $tier) {
                        if (isset($tier['quantity']) && (int)$tier['quantity'] === $newQuantity) {
                            $unitPrice = isset($tier['unit_price']) ? (float)$tier['unit_price'] : $unitPrice;
                            break;
                        }
                    }
                }
            }
            
            $cart->unit_price = $unitPrice;
            $cart->total_price = $unitPrice * $newQuantity;
            $cart->save();
            
            // Reload with relationships for response
            $cart->load('product');

            Log::info('Cart item updated', [
                'cart_id' => $cartId,
                'quantity' => $newQuantity,
                'unit_price' => $unitPrice,
                'total_price' => $cart->total_price,
                'selected_attributes' => $cart->selected_attributes
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Cart updated successfully',
                'data' => $cart
            ]);
        } catch (\Exception $e) {
            Log::error('Cart#update failed', ['e' => $e->getMessage(), 'cartId' => $cartId]);
            return response()->json(['success' => false, 'message' => 'Failed to update cart'], 500);
        }
    }

    // DELETE /api/cart/remove/{id} (auth required)
    public function removeFromCart(Request $request, $cartId)
    {
        $userId = auth()->id();
        Cart::where('user_id', $userId)->findOrFail($cartId)->delete();
        return response()->json(['success' => true, 'message' => 'Item removed']);
    }

    // DELETE /api/cart/clear (auth required)
    public function clearCart(Request $request)
    {
        $userId = auth()->id();
        Cart::where('user_id', $userId)->delete();
        return response()->json(['success' => true, 'message' => 'Cart cleared']);
    }

    // GET /api/cart/count (auth required)
    public function getCartCount(Request $request)
    {
        $userId = auth()->id();
        $count = Cart::where('user_id', $userId)->sum('quantity');
        return response()->json(['success' => true, 'count' => $count]);
    }

    // GET /api/cart/total (auth required)
    public function getCartTotal(Request $request)
    {
        $userId = auth()->id();
        $total = Cart::where('user_id', $userId)->sum('total_price');
        return response()->json(['success' => true, 'total' => $total]);
    }

    // POST /api/cart/{cartId}/upload-designs (auth required)
    public function uploadDesigns(Request $request, $cartId)
    {
        $validator = Validator::make($request->all(), [
            'front_design' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB
            'back_design' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ], [
            'front_design.image' => 'Front design must be a valid image.',
            'front_design.mimes' => 'Front design must be JPG, PNG, or WebP.',
            'front_design.max' => 'Front design must not exceed 10MB.',
            'back_design.image' => 'Back design must be a valid image.',
            'back_design.mimes' => 'Back design must be JPG, PNG, or WebP.',
            'back_design.max' => 'Back design must not exceed 10MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $userId = auth()->id();
            $cart = Cart::where('user_id', $userId)->findOrFail($cartId);

            $frontPath = $cart->front_design_path;
            $backPath = $cart->back_design_path;

            // Handle front design upload
            if ($request->hasFile('front_design')) {
                // Delete old front design if exists
                if ($frontPath) {
                    Storage::disk('public')->delete($frontPath);
                }

                $frontPath = $this->processDesignUpload($request->file('front_design'), 'front');
            }

            // Handle back design upload
            if ($request->hasFile('back_design')) {
                // Delete old back design if exists
                if ($backPath) {
                    Storage::disk('public')->delete($backPath);
                }

                $backPath = $this->processDesignUpload($request->file('back_design'), 'back');
            }

            $cart->update([
                'front_design_path' => $frontPath,
                'back_design_path' => $backPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Designs uploaded successfully',
                'data' => [
                    'front_design_url' => $cart->fresh()->front_design_url,
                    'back_design_url' => $cart->fresh()->back_design_url,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Cart#uploadDesigns failed', ['e' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to upload designs'], 500);
        }
    }

    // DELETE /api/cart/{cartId}/designs/{side} (auth required)
    public function deleteDesign(Request $request, $cartId, $side)
    {
        if (!in_array($side, ['front', 'back'])) {
            return response()->json(['success' => false, 'message' => 'Invalid design side'], 400);
        }

        try {
            $userId = auth()->id();
            $cart = Cart::where('user_id', $userId)->findOrFail($cartId);

            $pathField = $side . '_design_path';
            $currentPath = $cart->{$pathField};

            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
                $cart->update([$pathField => null]);
            }

            return response()->json(['success' => true, 'message' => ucfirst($side) . ' design deleted']);
        } catch (\Exception $e) {
            Log::error('Cart#deleteDesign failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete design'], 500);
        }
    }

    // Helper method to process and save design uploads
    private function processDesignUpload($file, $side)
    {
        try {
            $img = Image::read($file);

            // Scale down if too large, maintaining aspect ratio
            $img = $img->scaleDown(2000);

            $filename = $side . '_' . time() . '_' . Str::random(8) . '.jpg';
            $relative = 'cart-designs/' . $filename;
            $absolute = storage_path('app/public/' . $relative);

            // Ensure directory exists
            if (!is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0755, true);
            }

            // Save as JPEG quality 85
            $img->toJpeg(85)->save($absolute);

            return $relative;
        } catch (\Exception $e) {
            Log::error('processDesignUpload failed', ['e' => $e->getMessage()]);
            throw $e;
        }
    }
}
