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
     */
    public function finalize(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)->findOrFail($id);

            // Check if at least one side has a design
            if (!$design->has_any_design) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload at least one design before finalizing',
                ], 400);
            }

            $request->validate([
                'name' => 'nullable|string|max:255',
            ]);

            $design->update([
                'status' => 'completed',
                'name' => $request->name ?? $design->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Design finalized successfully',
                'data' => $design,
            ]);
        } catch (\Exception $e) {
            Log::error('Error finalizing design: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize design',
            ], 500);
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
                'front_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:25600',
                'back_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:25600',
            ]);

            $updateData = [];

            // Save canvas states
            if ($request->has('front_canvas_state')) {
                $updateData['front_canvas_state'] = $request->front_canvas_state;
            }
            if ($request->has('back_canvas_state')) {
                $updateData['back_canvas_state'] = $request->back_canvas_state;
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
     */
    public function getForEditing($id)
    {
        try {
            $user = Auth::user();

            $design = UserDesign::where('user_id', $user->id)
                ->with('product:id,name,slug')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'design' => $design,
                    'front_canvas_state' => $design->front_canvas_state,
                    'back_canvas_state' => $design->back_canvas_state,
                    'front_image_url' => $design->front_original_url,
                    'back_image_url' => $design->back_original_url,
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
