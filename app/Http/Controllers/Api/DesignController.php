<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDesign;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class DesignController extends Controller
{
    /**
     * Get all designs for the authenticated user (My Projects)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $designs = UserDesign::where('user_id', $user->id)
                ->with('product:id,name,slug,featured_image')
                ->orderBy('updated_at', 'desc')
                ->paginate(12);

            return response()->json([
                'success' => true,
                'data' => $designs,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching designs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch designs',
            ], 500);
        }
    }

    /**
     * Get a specific design
     */
    public function show($id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)
                ->with('product')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $design,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Design not found',
            ], 404);
        }
    }

    /**
     * Create a new design (initial creation when entering design studio)
     *
     * Design Types:
     * - 'uploaded': User uploads their own design file
     * - 'customized': User customizes a template (Browse Design)
     * - 'blank': User creates design from scratch (no template)
     */
    public function store(Request $request)
    {
        try {
            // Allow both authenticated users and guests (via session_id)
            $user = Auth::user();

            $request->validate([
                'product_id' => 'required|exists:products,id',
                'orientation' => 'in:horizontal,vertical',
                'name' => 'nullable|string|max:255',
                'quantity' => 'nullable|integer|min:1',
                'selected_shape' => 'nullable|string|max:255',
                'selected_finishing' => 'nullable|string|max:255',
                'session_id' => 'nullable|string|max:255',
                // Design type fields
                'design_type' => 'nullable|in:uploaded,customized,blank',
                'template_id' => 'nullable|exists:templates,id',
                'color_variant_id' => 'nullable|exists:template_color_variants,id',
            ]);

            $product = Product::find($request->product_id);

            $design = UserDesign::create([
                'user_id' => $user?->id,
                'session_id' => $request->session_id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity ?? 1,
                'selected_shape' => $request->selected_shape,
                'selected_finishing' => $request->selected_finishing,
                'name' => $request->name ?? $product->name . ' Design',
                'orientation' => $request->orientation ?? 'horizontal',
                'status' => 'draft',
                // Design type fields - helps distinguish Browse Design vs Upload Design
                'design_type' => $request->design_type ?? 'blank',
                'template_id' => $request->template_id,
                'color_variant_id' => $request->color_variant_id,
            ]);

            Log::info('Design created:', [
                'design_id' => $design->id,
                'design_type' => $design->design_type,
                'template_id' => $design->template_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Design created successfully',
                'data' => $design,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create design: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload design image (front or back)
     */
    public function uploadImage(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'side' => 'required|in:front,back',
                'image' => 'required|file|mimes:jpeg,jpg,png,webp|max:25600', // 25MB max
                'position_data' => 'nullable|json',
            ]);

            $side = $request->side;
            $file = $request->file('image');

            // Generate unique filename
            $filename = Str::uuid() . '_' . $side . '.' . $file->getClientOriginalExtension();
            $directory = 'designs/' . $user->id;

            // Store original file
            $originalPath = $file->storeAs($directory . '/originals', $filename, 'public');

            // Create thumbnail (400x250 for preview)
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $directory . '/thumbnails/' . $thumbnailFilename;

            // Ensure thumbnail directory exists
            Storage::disk('public')->makeDirectory($directory . '/thumbnails');

            // Create thumbnail using Intervention Image
            $image = Image::read($file->getRealPath());
            $image->cover(400, 250);
            $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);
            $image->save($thumbnailFullPath);

            // Delete old files if exists
            $oldOriginalPath = $side === 'front' ? $design->front_original_path : $design->back_original_path;
            $oldThumbnailPath = $side === 'front' ? $design->front_thumbnail_path : $design->back_thumbnail_path;

            if ($oldOriginalPath) {
                Storage::disk('public')->delete($oldOriginalPath);
            }
            if ($oldThumbnailPath) {
                Storage::disk('public')->delete($oldThumbnailPath);
            }

            // Update design record
            $updateData = [];
            if ($side === 'front') {
                $updateData['front_original_path'] = $originalPath;
                $updateData['front_thumbnail_path'] = $thumbnailPath;
                if ($request->position_data) {
                    $updateData['front_position_data'] = json_decode($request->position_data, true);
                }
            } else {
                $updateData['back_original_path'] = $originalPath;
                $updateData['back_thumbnail_path'] = $thumbnailPath;
                if ($request->position_data) {
                    $updateData['back_position_data'] = json_decode($request->position_data, true);
                }
            }

            // If design_type is still 'blank' and no template, mark as 'uploaded'
            // This distinguishes "Upload Design" from "Browse Design" flows
            if ($design->design_type === 'blank' && !$design->template_id) {
                $updateData['design_type'] = 'uploaded';
            }

            $design->update($updateData);
            $design->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($side) . ' design uploaded successfully',
                'data' => [
                    'design' => $design,
                    'uploaded_side' => $side,
                    'original_url' => $side === 'front' ? $design->front_original_url : $design->back_original_url,
                    'thumbnail_url' => $side === 'front' ? $design->front_thumbnail_url : $design->back_thumbnail_url,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading design image: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update position data for a design side
     */
    public function updatePosition(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'side' => 'required|in:front,back',
                'position_data' => 'required|array',
                'position_data.x' => 'required|numeric',
                'position_data.y' => 'required|numeric',
                'position_data.width' => 'required|numeric',
                'position_data.height' => 'required|numeric',
                'position_data.rotation' => 'nullable|numeric',
            ]);

            $side = $request->side;
            $positionData = $request->position_data;

            if ($side === 'front') {
                $design->front_position_data = $positionData;
            } else {
                $design->back_position_data = $positionData;
            }

            $design->save();

            return response()->json([
                'success' => true,
                'message' => 'Position updated successfully',
                'data' => $design,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating position: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update position',
            ], 500);
        }
    }

    /**
     * Finalize design (mark as completed)
     * CRITICAL: This now accepts preview images with text layers rendered
     * These previews are what gets displayed in the Cart page
     */
    public function finalize(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'name' => 'nullable|string|max:255',
                'front_preview' => 'nullable|string', // Base64 data URL
                'back_preview' => 'nullable|string',  // Base64 data URL
                'front_text_layers' => 'nullable|array',
                'back_text_layers' => 'nullable|array',
                'orientation' => 'nullable|string|in:horizontal,vertical',
                'shape' => 'nullable|string|in:rectangle,rounded',
            ]);

            // Check if we have preview data or existing design
            $hasFrontPreview = $request->filled('front_preview');
            $hasBackPreview = $request->filled('back_preview');
            $hasExistingDesign = $design->has_any_design;

            Log::info('Design finalize request received:', [
                'design_id' => $id,
                'has_front_preview' => $hasFrontPreview,
                'has_back_preview' => $hasBackPreview,
                'has_existing_design' => $hasExistingDesign,
                'front_preview_length' => $hasFrontPreview ? strlen($request->front_preview) : 0,
                'back_preview_length' => $hasBackPreview ? strlen($request->back_preview) : 0,
                'front_preview_is_data_url' => $hasFrontPreview ? str_starts_with($request->front_preview, 'data:') : false,
                'back_preview_is_data_url' => $hasBackPreview ? str_starts_with($request->back_preview, 'data:') : false,
            ]);

            if (!$hasFrontPreview && !$hasBackPreview && !$hasExistingDesign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload at least one design before finalizing',
                ], 400);
            }

            $updateData = [
                'status' => 'completed',
                'name' => $request->name ?? $design->name,
            ];

            // Save orientation and shape if provided
            if ($request->filled('orientation')) {
                $updateData['orientation'] = $request->orientation;
            }
            if ($request->filled('shape')) {
                $updateData['selected_shape'] = $request->shape;
            }

            // Save text layers as JSON for future editing
            if ($request->has('front_text_layers')) {
                $updateData['front_text_layers'] = $request->front_text_layers;
                Log::info('[finalize] Saving front_text_layers:', [
                    'count' => is_array($request->front_text_layers) ? count($request->front_text_layers) : 0,
                    'data' => $request->front_text_layers,
                ]);
            }
            if ($request->has('back_text_layers')) {
                $updateData['back_text_layers'] = $request->back_text_layers;
                Log::info('[finalize] Saving back_text_layers:', [
                    'count' => is_array($request->back_text_layers) ? count($request->back_text_layers) : 0,
                    'data' => $request->back_text_layers,
                ]);
            }

            $directory = 'designs/' . $user->id;

            // CRITICAL: Save the preview images (with text layers baked in)
            // These are what get displayed in the Cart page
            if ($hasFrontPreview) {
                $frontPath = $this->saveBase64Image($request->front_preview, $directory, 'front_preview');
                if ($frontPath) {
                    // Delete old preview if exists
                    if ($design->front_preview_path) {
                        Storage::disk('public')->delete($design->front_preview_path);
                    }
                    $updateData['front_preview_path'] = $frontPath;
                    Log::info('Saved front preview to: ' . $frontPath);
                }
            }

            if ($hasBackPreview) {
                $backPath = $this->saveBase64Image($request->back_preview, $directory, 'back_preview');
                if ($backPath) {
                    // Delete old preview if exists
                    if ($design->back_preview_path) {
                        Storage::disk('public')->delete($design->back_preview_path);
                    }
                    $updateData['back_preview_path'] = $backPath;
                    Log::info('Saved back preview to: ' . $backPath);
                }
            }

            $design->update($updateData);
            $design->refresh();

            Log::info('Design finalized:', [
                'design_id' => $design->id,
                'has_front_preview' => !!$design->front_preview_path,
                'has_back_preview' => !!$design->back_preview_path,
                'front_preview_url' => $design->front_preview_url,
                'back_preview_url' => $design->back_preview_url,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Design finalized successfully',
                'data' => $design,
            ]);
        } catch (\Exception $e) {
            Log::error('Error finalizing design: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize design: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save base64 encoded image to storage
     * @param string $base64Data Base64 data URL (data:image/png;base64,...)
     * @param string $directory Target directory
     * @param string $prefix Filename prefix
     * @return string|null Stored file path or null on failure
     */
    private function saveBase64Image($base64Data, $directory, $prefix)
    {
        try {
            Log::info('saveBase64Image called for ' . $prefix, [
                'data_length' => strlen($base64Data),
                'data_start' => substr($base64Data, 0, 50),
                'is_data_url' => str_starts_with($base64Data, 'data:'),
            ]);

            // Check if it's a base64 data URL
            if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                Log::warning('Invalid base64 image format for ' . $prefix . '. Data starts with: ' . substr($base64Data, 0, 100));
                return null;
            }

            $extension = $matches[1];
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }

            // Remove the data URL prefix
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                Log::error('Failed to decode base64 image for ' . $prefix);
                return null;
            }

            // Generate unique filename
            $filename = Str::uuid() . '_' . $prefix . '.' . $extension;
            $previewDir = $directory . '/previews';
            $fullPath = $previewDir . '/' . $filename;

            // Ensure directory exists
            Storage::disk('public')->makeDirectory($previewDir);

            // Save the file
            Storage::disk('public')->put($fullPath, $imageData);

            Log::info('Saved base64 image: ' . $fullPath . ' (' . strlen($imageData) . ' bytes)');

            return $fullPath;
        } catch (\Exception $e) {
            Log::error('Error saving base64 image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update design details (name, orientation)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'name' => 'nullable|string|max:255',
                'orientation' => 'nullable|in:horizontal,vertical',
                'quantity' => 'nullable|integer|min:1',
                'selected_shape' => 'nullable|string|max:255',
                'selected_finishing' => 'nullable|string|max:255',
            ]);

            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('orientation')) {
                $updateData['orientation'] = $request->orientation;
            }
            if ($request->has('quantity')) {
                $updateData['quantity'] = $request->quantity;
            }
            if ($request->has('selected_shape')) {
                $updateData['selected_shape'] = $request->selected_shape;
            }
            if ($request->has('selected_finishing')) {
                $updateData['selected_finishing'] = $request->selected_finishing;
            }

            $design->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Design updated successfully',
                'data' => $design,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update design',
            ], 500);
        }
    }

    /**
     * Delete a design
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            // Check if design is used in any cart items
            if ($design->cartItems()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete design that is in your cart. Remove it from cart first.',
                ], 400);
            }

            $design->delete(); // Files are deleted in model's deleting event

            return response()->json([
                'success' => true,
                'message' => 'Design deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete design',
            ], 500);
        }
    }

    /**
     * Duplicate a design
     */
    public function duplicate($id)
    {
        try {
            $user = Auth::user();

            $originalDesign = UserDesign::where('user_id', $user->id)->findOrFail($id);

            // Create new design
            $newDesign = UserDesign::create([
                'user_id' => $user->id,
                'product_id' => $originalDesign->product_id,
                'name' => $originalDesign->name . ' (Copy)',
                'orientation' => $originalDesign->orientation,
                'front_position_data' => $originalDesign->front_position_data,
                'back_position_data' => $originalDesign->back_position_data,
                'status' => 'draft',
            ]);

            // Copy files
            $directory = 'designs/' . $user->id;

            if ($originalDesign->front_original_path) {
                $newFrontOriginal = $directory . '/originals/' . Str::uuid() . '_front.' . pathinfo($originalDesign->front_original_path, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($originalDesign->front_original_path, $newFrontOriginal);
                $newDesign->front_original_path = $newFrontOriginal;
            }

            if ($originalDesign->front_thumbnail_path) {
                $newFrontThumb = $directory . '/thumbnails/thumb_' . Str::uuid() . '_front.' . pathinfo($originalDesign->front_thumbnail_path, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($originalDesign->front_thumbnail_path, $newFrontThumb);
                $newDesign->front_thumbnail_path = $newFrontThumb;
            }

            if ($originalDesign->back_original_path) {
                $newBackOriginal = $directory . '/originals/' . Str::uuid() . '_back.' . pathinfo($originalDesign->back_original_path, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($originalDesign->back_original_path, $newBackOriginal);
                $newDesign->back_original_path = $newBackOriginal;
            }

            if ($originalDesign->back_thumbnail_path) {
                $newBackThumb = $directory . '/thumbnails/thumb_' . Str::uuid() . '_back.' . pathinfo($originalDesign->back_thumbnail_path, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($originalDesign->back_thumbnail_path, $newBackThumb);
                $newDesign->back_thumbnail_path = $newBackThumb;
            }

            $newDesign->save();

            return response()->json([
                'success' => true,
                'message' => 'Design duplicated successfully',
                'data' => $newDesign,
            ]);
        } catch (\Exception $e) {
            Log::error('Error duplicating design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate design',
            ], 500);
        }
    }

    /**
     * Copy image from user's upload library to design
     * This allows reusing previously uploaded images in new designs
     */
    public function copyFromUpload(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'upload_id' => 'required|exists:user_uploads,id',
                'side' => 'required|in:front,back',
            ]);

            $upload = \App\Models\UserUpload::where('id', $request->upload_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if (!$upload->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload has no file',
                ], 400);
            }

            $side = $request->side;
            $directory = 'designs/' . $user->id;

            // Copy the original file to designs folder
            $extension = pathinfo($upload->file_path, PATHINFO_EXTENSION);
            $filename = Str::uuid() . '_' . $side . '.' . $extension;
            $originalPath = $directory . '/originals/' . $filename;

            Storage::disk('public')->copy($upload->file_path, $originalPath);

            // Create thumbnail
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $directory . '/thumbnails/' . $thumbnailFilename;

            Storage::disk('public')->makeDirectory($directory . '/thumbnails');

            $image = Image::read(Storage::disk('public')->path($originalPath));
            $image->cover(400, 250);
            $image->save(Storage::disk('public')->path($thumbnailPath));

            // Delete old files if exists
            $oldOriginalPath = $side === 'front' ? $design->front_original_path : $design->back_original_path;
            $oldThumbnailPath = $side === 'front' ? $design->front_thumbnail_path : $design->back_thumbnail_path;

            if ($oldOriginalPath) {
                Storage::disk('public')->delete($oldOriginalPath);
            }
            if ($oldThumbnailPath) {
                Storage::disk('public')->delete($oldThumbnailPath);
            }

            // Update design record
            $updateData = [];
            if ($side === 'front') {
                $updateData['front_original_path'] = $originalPath;
                $updateData['front_thumbnail_path'] = $thumbnailPath;
            } else {
                $updateData['back_original_path'] = $originalPath;
                $updateData['back_thumbnail_path'] = $thumbnailPath;
            }

            $design->update($updateData);
            $design->refresh();

            // Increment usage count on the upload
            $upload->incrementUsage();

            return response()->json([
                'success' => true,
                'message' => ucfirst($side) . ' design copied from library successfully',
                'data' => [
                    'design' => $design,
                    'copied_side' => $side,
                    'original_url' => $side === 'front' ? $design->front_original_url : $design->back_original_url,
                    'thumbnail_url' => $side === 'front' ? $design->front_thumbnail_url : $design->back_thumbnail_url,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error copying upload to design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to copy image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save canvas state (for resuming editing later)
     * This stores the complete canvas state including image positions, sizes, etc.
     */
    public function saveCanvasState(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'front_canvas_state' => 'nullable|array',
                'back_canvas_state' => 'nullable|array',
                'front_text_layers' => 'nullable|array',
                'back_text_layers' => 'nullable|array',
                'front_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:25600',
                'back_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:25600',
            ]);

            $updateData = [];

            // Save canvas states (image position, size, rotation)
            if ($request->has('front_canvas_state')) {
                $updateData['front_canvas_state'] = $request->front_canvas_state;
            }
            if ($request->has('back_canvas_state')) {
                $updateData['back_canvas_state'] = $request->back_canvas_state;
            }

            // Save text layers (text content, formatting, position)
            if ($request->has('front_text_layers')) {
                $updateData['front_text_layers'] = $request->front_text_layers;
            }
            if ($request->has('back_text_layers')) {
                $updateData['back_text_layers'] = $request->back_text_layers;
            }

            // Handle image uploads if provided
            $directory = 'designs/' . $user->id;

            if ($request->hasFile('front_image')) {
                $file = $request->file('front_image');
                $filename = Str::uuid() . '_front.' . $file->getClientOriginalExtension();

                // Store original
                $originalPath = $file->storeAs($directory . '/originals', $filename, 'public');

                // Create thumbnail
                $thumbnailFilename = 'thumb_' . $filename;
                $thumbnailPath = $directory . '/thumbnails/' . $thumbnailFilename;
                Storage::disk('public')->makeDirectory($directory . '/thumbnails');

                $image = Image::read($file->getRealPath());
                $image->cover(400, 250);
                $image->save(Storage::disk('public')->path($thumbnailPath));

                // Delete old files
                if ($design->front_original_path) {
                    Storage::disk('public')->delete($design->front_original_path);
                }
                if ($design->front_thumbnail_path) {
                    Storage::disk('public')->delete($design->front_thumbnail_path);
                }

                $updateData['front_original_path'] = $originalPath;
                $updateData['front_thumbnail_path'] = $thumbnailPath;
            }

            if ($request->hasFile('back_image')) {
                $file = $request->file('back_image');
                $filename = Str::uuid() . '_back.' . $file->getClientOriginalExtension();

                $originalPath = $file->storeAs($directory . '/originals', $filename, 'public');

                $thumbnailFilename = 'thumb_' . $filename;
                $thumbnailPath = $directory . '/thumbnails/' . $thumbnailFilename;
                Storage::disk('public')->makeDirectory($directory . '/thumbnails');

                $image = Image::read($file->getRealPath());
                $image->cover(400, 250);
                $image->save(Storage::disk('public')->path($thumbnailPath));

                if ($design->back_original_path) {
                    Storage::disk('public')->delete($design->back_original_path);
                }
                if ($design->back_thumbnail_path) {
                    Storage::disk('public')->delete($design->back_thumbnail_path);
                }

                $updateData['back_original_path'] = $originalPath;
                $updateData['back_thumbnail_path'] = $thumbnailPath;
            }

            if (!empty($updateData)) {
                $design->update($updateData);
            }

            $design->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Canvas state saved successfully',
                'data' => $design,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving canvas state: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save canvas state: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get design with full canvas state for editing
     * Returns ALL data needed to fully restore the design in CanvasDesignStudio:
     * - Canvas state (position, size, rotation)
     * - Image URLs (original images)
     * - Text layers (all text with formatting)
     * - Template data (if design was customized from a template)
     * - Design metadata (orientation, shape, type)
     */
    public function getForEditing($id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)
                ->with(['product:id,name,slug', 'template', 'colorVariant'])
                ->findOrFail($id);

            // IMPORTANT: For template-based designs, the image is stored in preview_path, not original_path
            // We need to use the preview URL as fallback when original URL is not available
            $frontImageUrl = $design->front_original_url ?? $design->front_preview_url;
            $backImageUrl = $design->back_original_url ?? $design->back_preview_url;

            Log::info('[getForEditing] Fetching design for editing:', [
                'design_id' => $id,
                'has_front_canvas_state' => !empty($design->front_canvas_state),
                'has_back_canvas_state' => !empty($design->back_canvas_state),
                'has_front_original' => !empty($design->front_original_path),
                'has_back_original' => !empty($design->back_original_path),
                'has_front_preview' => !empty($design->front_preview_path),
                'has_back_preview' => !empty($design->back_preview_path),
                'front_image_url' => $frontImageUrl ? 'present' : 'null',
                'back_image_url' => $backImageUrl ? 'present' : 'null',
                'has_front_text_layers' => !empty($design->front_text_layers),
                'has_back_text_layers' => !empty($design->back_text_layers),
                // Log actual text layer data for debugging
                'front_text_layers_count' => is_array($design->front_text_layers) ? count($design->front_text_layers) : 0,
                'back_text_layers_count' => is_array($design->back_text_layers) ? count($design->back_text_layers) : 0,
                'front_text_layers_data' => $design->front_text_layers,
                'back_text_layers_data' => $design->back_text_layers,
                'design_type' => $design->design_type,
                'template_id' => $design->template_id,
            ]);

            // Build template data if design was customized from a template
            $templateData = null;
            if ($design->template_id && $design->template) {
                $templateData = [
                    'template' => [
                        'id' => $design->template->id,
                        'name' => $design->template->name,
                        'preview_url' => $design->template->preview_url,
                        'front_template_url' => $design->template->front_template_url,
                        'back_template_url' => $design->template->back_template_url,
                    ],
                    'colorVariant' => $design->colorVariant ? [
                        'id' => $design->colorVariant->id,
                        'name' => $design->colorVariant->name,
                        'preview_url' => $design->colorVariant->preview_url,
                        'front_template_url' => $design->colorVariant->front_template_url,
                        'back_template_url' => $design->colorVariant->back_template_url,
                    ] : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'design' => $design,
                    // Canvas state for restoring exact image position/size
                    'front_canvas_state' => $design->front_canvas_state,
                    'back_canvas_state' => $design->back_canvas_state,
                    // Image URLs - use original for editing (high quality), fallback to preview
                    // CRITICAL: Template-based designs store image in preview_path, not original_path
                    'front_image_url' => $frontImageUrl,
                    'back_image_url' => $backImageUrl,
                    // Text layers for restoring all text with formatting
                    'front_text_layers' => $design->front_text_layers ?? [],
                    'back_text_layers' => $design->back_text_layers ?? [],
                    // Template data for restoring template-based designs
                    'template_data' => $templateData,
                    // Design metadata
                    'design_type' => $design->design_type,
                    'orientation' => $design->orientation,
                    'selected_shape' => $design->selected_shape,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting design for editing: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Design not found',
            ], 404);
        }
    }

    /**
     * Generate and save final images for ordering
     * Called when user places an order
     */
    public function saveFinalImages(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            $request->validate([
                'front_final' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:25600',
                'back_final' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:25600',
            ]);

            $directory = 'designs/' . $user->id . '/final';
            Storage::disk('public')->makeDirectory($directory);

            $updateData = [];

            if ($request->hasFile('front_final')) {
                $file = $request->file('front_final');
                $filename = Str::uuid() . '_front_final.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');

                // Delete old final image
                if ($design->front_final_path) {
                    Storage::disk('public')->delete($design->front_final_path);
                }

                $updateData['front_final_path'] = $path;
            }

            if ($request->hasFile('back_final')) {
                $file = $request->file('back_final');
                $filename = Str::uuid() . '_back_final.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');

                if ($design->back_final_path) {
                    Storage::disk('public')->delete($design->back_final_path);
                }

                $updateData['back_final_path'] = $path;
            }

            if (!empty($updateData)) {
                $design->update($updateData);
            }

            $design->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Final images saved successfully',
                'data' => [
                    'front_final_url' => $design->front_final_url,
                    'back_final_url' => $design->back_final_url,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving final images: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save final images',
            ], 500);
        }
    }
}
