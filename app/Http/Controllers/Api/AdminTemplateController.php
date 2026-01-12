<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\TemplateColorVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class AdminTemplateController extends Controller
{
    /**
     * Get all templates (admin view with inactive ones)
     */
    public function index(Request $request)
    {
        try {
            $query = Template::with(['colorVariants', 'category', 'subcategory'])
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc');

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('category_id')) {
                $query->byCategory($request->category_id);
            }

            $templates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching templates for admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates',
            ], 500);
        }
    }

    /**
     * Create new template
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'subcategory_id' => 'nullable|exists:subcategories,id',
                'preview_image' => 'required|image|max:10240', // 10MB
                'front_template' => 'nullable|image|max:10240',
                'back_template' => 'nullable|image|max:10240',
                'orientation' => 'required|in:horizontal,vertical',
                'corners' => 'required|in:rectangle,rounded',
                'base_price' => 'required|numeric|min:0',
                'print_width_inches' => 'nullable|numeric',
                'print_length_inches' => 'nullable|numeric',
                'is_active' => 'boolean',
                'is_featured' => 'boolean',
                'sort_order' => 'nullable|integer',
            ]);

            $directory = 'templates/' . Str::slug($validated['name']) . '_' . time();

            // Upload preview image
            if ($request->hasFile('preview_image')) {
                $previewPath = $this->uploadImage($request->file('preview_image'), $directory, 'preview');
                $validated['preview_image'] = $previewPath;
            }

            // Upload front template
            if ($request->hasFile('front_template')) {
                $frontPath = $this->uploadImage($request->file('front_template'), $directory, 'front');
                $validated['front_template_path'] = $frontPath;
            }

            // Upload back template
            if ($request->hasFile('back_template')) {
                $backPath = $this->uploadImage($request->file('back_template'), $directory, 'back');
                $validated['back_template_path'] = $backPath;
            }

            $template = Template::create($validated);

            return response()->json([
                'success' => true,
                'data' => $template->load(['colorVariants', 'category']),
                'message' => 'Template created successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing template
     */
    public function update(Request $request, $id)
    {
        try {
            $template = Template::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'sometimes|exists:categories,id',
                'subcategory_id' => 'nullable|exists:subcategories,id',
                'preview_image' => 'nullable|image|max:10240',
                'front_template' => 'nullable|image|max:10240',
                'back_template' => 'nullable|image|max:10240',
                'orientation' => 'sometimes|in:horizontal,vertical',
                'corners' => 'sometimes|in:rectangle,rounded',
                'base_price' => 'sometimes|numeric|min:0',
                'print_width_inches' => 'nullable|numeric',
                'print_length_inches' => 'nullable|numeric',
                'is_active' => 'boolean',
                'is_featured' => 'boolean',
                'sort_order' => 'nullable|integer',
            ]);

            $directory = 'templates/' . Str::slug($template->name) . '_' . $template->id;

            // Update preview image if provided
            if ($request->hasFile('preview_image')) {
                // Delete old image
                if ($template->preview_image) {
                    Storage::disk('public')->delete($template->preview_image);
                }
                $previewPath = $this->uploadImage($request->file('preview_image'), $directory, 'preview');
                $validated['preview_image'] = $previewPath;
            }

            // Update front template if provided
            if ($request->hasFile('front_template')) {
                if ($template->front_template_path) {
                    Storage::disk('public')->delete($template->front_template_path);
                }
                $frontPath = $this->uploadImage($request->file('front_template'), $directory, 'front');
                $validated['front_template_path'] = $frontPath;
            }

            // Update back template if provided
            if ($request->hasFile('back_template')) {
                if ($template->back_template_path) {
                    Storage::disk('public')->delete($template->back_template_path);
                }
                $backPath = $this->uploadImage($request->file('back_template'), $directory, 'back');
                $validated['back_template_path'] = $backPath;
            }

            $template->update($validated);

            return response()->json([
                'success' => true,
                'data' => $template->load(['colorVariants', 'category']),
                'message' => 'Template updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
            ], 500);
        }
    }

    /**
     * Delete template
     */
    public function destroy($id)
    {
        try {
            $template = Template::findOrFail($id);

            // Delete all images
            if ($template->preview_image) {
                Storage::disk('public')->delete($template->preview_image);
            }
            if ($template->front_template_path) {
                Storage::disk('public')->delete($template->front_template_path);
            }
            if ($template->back_template_path) {
                Storage::disk('public')->delete($template->back_template_path);
            }

            // Delete color variants
            foreach ($template->colorVariants as $variant) {
                if ($variant->preview_image) {
                    Storage::disk('public')->delete($variant->preview_image);
                }
                if ($variant->front_template_path) {
                    Storage::disk('public')->delete($variant->front_template_path);
                }
                if ($variant->back_template_path) {
                    Storage::disk('public')->delete($variant->back_template_path);
                }
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
            ], 500);
        }
    }

    /**
     * Add color variant to template
     */
    public function addColorVariant(Request $request, $templateId)
    {
        try {
            $template = Template::findOrFail($templateId);

            $validated = $request->validate([
                'color_name' => 'required|string|max:255',
                'color_hex' => 'required|string|max:7', // #RRGGBB
                'preview_image' => 'required|image|max:10240',
                'front_template' => 'nullable|image|max:10240',
                'back_template' => 'nullable|image|max:10240',
                'sort_order' => 'nullable|integer',
            ]);

            $directory = 'templates/' . Str::slug($template->name) . '_' . $template->id . '/variants';

            // Upload variant preview
            if ($request->hasFile('preview_image')) {
                $previewPath = $this->uploadImage($request->file('preview_image'), $directory, 'preview_' . Str::slug($validated['color_name']));
                $validated['preview_image'] = $previewPath;
            }

            // Upload front template
            if ($request->hasFile('front_template')) {
                $frontPath = $this->uploadImage($request->file('front_template'), $directory, 'front_' . Str::slug($validated['color_name']));
                $validated['front_template_path'] = $frontPath;
            }

            // Upload back template
            if ($request->hasFile('back_template')) {
                $backPath = $this->uploadImage($request->file('back_template'), $directory, 'back_' . Str::slug($validated['color_name']));
                $validated['back_template_path'] = $backPath;
            }

            $validated['template_id'] = $template->id;
            $variant = TemplateColorVariant::create($validated);

            return response()->json([
                'success' => true,
                'data' => $variant,
                'message' => 'Color variant added successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding color variant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add color variant',
            ], 500);
        }
    }

    /**
     * Update color variant
     */
    public function updateColorVariant(Request $request, $templateId, $variantId)
    {
        try {
            $variant = TemplateColorVariant::where('template_id', $templateId)
                ->findOrFail($variantId);

            $validated = $request->validate([
                'color_name' => 'sometimes|string|max:255',
                'color_hex' => 'sometimes|string|max:7',
                'preview_image' => 'nullable|image|max:10240',
                'front_template' => 'nullable|image|max:10240',
                'back_template' => 'nullable|image|max:10240',
                'sort_order' => 'nullable|integer',
            ]);

            $directory = 'templates/' . Str::slug($variant->template->name) . '_' . $templateId . '/variants';

            // Update preview if provided
            if ($request->hasFile('preview_image')) {
                if ($variant->preview_image) {
                    Storage::disk('public')->delete($variant->preview_image);
                }
                $previewPath = $this->uploadImage($request->file('preview_image'), $directory, 'preview_' . Str::slug($validated['color_name'] ?? $variant->color_name));
                $validated['preview_image'] = $previewPath;
            }

            // Update front template if provided
            if ($request->hasFile('front_template')) {
                if ($variant->front_template_path) {
                    Storage::disk('public')->delete($variant->front_template_path);
                }
                $frontPath = $this->uploadImage($request->file('front_template'), $directory, 'front_' . Str::slug($validated['color_name'] ?? $variant->color_name));
                $validated['front_template_path'] = $frontPath;
            }

            // Update back template if provided
            if ($request->hasFile('back_template')) {
                if ($variant->back_template_path) {
                    Storage::disk('public')->delete($variant->back_template_path);
                }
                $backPath = $this->uploadImage($request->file('back_template'), $directory, 'back_' . Str::slug($validated['color_name'] ?? $variant->color_name));
                $validated['back_template_path'] = $backPath;
            }

            $variant->update($validated);

            return response()->json([
                'success' => true,
                'data' => $variant,
                'message' => 'Color variant updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating color variant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update color variant',
            ], 500);
        }
    }

    /**
     * Delete color variant
     */
    public function deleteColorVariant($templateId, $variantId)
    {
        try {
            $variant = TemplateColorVariant::where('template_id', $templateId)
                ->findOrFail($variantId);

            // Delete images
            if ($variant->preview_image) {
                Storage::disk('public')->delete($variant->preview_image);
            }
            if ($variant->front_template_path) {
                Storage::disk('public')->delete($variant->front_template_path);
            }
            if ($variant->back_template_path) {
                Storage::disk('public')->delete($variant->back_template_path);
            }

            $variant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Color variant deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting color variant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete color variant',
            ], 500);
        }
    }

    /**
     * Helper: Upload and process image
     */
    private function uploadImage($file, $directory, $prefix)
    {
        $filename = $prefix . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;

        Storage::disk('public')->makeDirectory($directory);
        $file->storeAs($directory, $filename, 'public');

        return $path;
    }
}
