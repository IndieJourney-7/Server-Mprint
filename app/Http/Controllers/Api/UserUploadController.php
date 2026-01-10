<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UserUploadController extends Controller
{
    /**
     * Get all uploads for the authenticated user
     * Supports filtering by recent, favorites, folder
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = UserUpload::where('user_id', $user->id);

            // Filter by folder
            if ($request->filled('folder')) {
                $query->where('folder', $request->folder);
            }

            // Filter favorites only
            if ($request->boolean('favorites')) {
                $query->where('is_favorite', true);
            }

            // Order options
            $orderBy = $request->get('order_by', 'created_at');
            $orderDir = $request->get('order_dir', 'desc');
            
            if ($orderBy === 'last_used') {
                $query->orderByRaw('COALESCE(last_used_at, created_at) DESC');
            } else {
                $query->orderBy($orderBy, $orderDir);
            }

            $perPage = (int) $request->get('per_page', 20);
            $uploads = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $uploads,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user uploads: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch uploads',
            ], 500);
        }
    }

    /**
     * Get recent uploads (for quick access in design studio)
     */
    public function recent(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = (int) $request->get('limit', 20);

            $uploads = UserUpload::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $uploads,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching recent uploads: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent uploads',
            ], 500);
        }
    }

    /**
     * Upload a new image
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'image' => 'required|file|mimes:jpeg,jpg,png,webp,gif|max:25600', // 25MB
                'folder' => 'nullable|string|max:100',
            ]);

            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $directory = 'uploads/' . $user->id;

            // Store original file
            $filePath = $file->storeAs($directory . '/originals', $filename, 'public');

            // Get image dimensions
            $imageInfo = getimagesize($file->getRealPath());
            $width = $imageInfo[0] ?? null;
            $height = $imageInfo[1] ?? null;

            // Create thumbnail (200x200 for grid view)
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $directory . '/thumbnails/' . $thumbnailFilename;

            // Ensure thumbnail directory exists
            Storage::disk('public')->makeDirectory($directory . '/thumbnails');

            // Create thumbnail using Intervention Image
            $image = Image::read($file->getRealPath());
            $image->cover(200, 200);
            $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);
            $image->save($thumbnailFullPath);

            // Create database record
            $upload = UserUpload::create([
                'user_id' => $user->id,
                'original_name' => $originalName,
                'file_path' => $filePath,
                'thumbnail_path' => $thumbnailPath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'folder' => $request->folder,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => $upload,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading image: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark an upload as used (increment usage count)
     */
    public function markUsed($id)
    {
        try {
            $user = Auth::user();
            $upload = UserUpload::where('user_id', $user->id)->findOrFail($id);
            
            $upload->incrementUsage();

            return response()->json([
                'success' => true,
                'message' => 'Upload marked as used',
                'data' => $upload,
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking upload as used: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update upload',
            ], 500);
        }
    }

    /**
     * Toggle favorite status
     */
    public function toggleFavorite($id)
    {
        try {
            $user = Auth::user();
            $upload = UserUpload::where('user_id', $user->id)->findOrFail($id);
            
            $upload->update(['is_favorite' => !$upload->is_favorite]);

            return response()->json([
                'success' => true,
                'message' => $upload->is_favorite ? 'Added to favorites' : 'Removed from favorites',
                'data' => $upload,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling favorite: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update upload',
            ], 500);
        }
    }

    /**
     * Delete an upload
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $upload = UserUpload::where('user_id', $user->id)->findOrFail($id);
            
            // Model's boot method handles file deletion
            $upload->delete();

            return response()->json([
                'success' => true,
                'message' => 'Upload deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete upload',
            ], 500);
        }
    }

    /**
     * Bulk delete uploads
     */
    public function bulkDestroy(Request $request)
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer',
            ]);

            $deleted = 0;
            foreach ($request->ids as $id) {
                $upload = UserUpload::where('user_id', $user->id)->find($id);
                if ($upload) {
                    $upload->delete();
                    $deleted++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "$deleted uploads deleted successfully",
            ]);
        } catch (\Exception $e) {
            Log::error('Error bulk deleting uploads: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete uploads',
            ], 500);
        }
    }
}
