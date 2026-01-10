<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSession;
use App\Models\UserDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkSessionController extends Controller
{
    public function initOrRestore(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'session_id' => 'nullable|string',
            'orientation' => 'nullable|string|in:horizontal,vertical',
            'shape' => 'nullable|string|in:rectangle,rounded',
            'selected_attributes' => 'nullable|array',
        ]);

        $userId = $request->user()->id ?? null;

        if ($request->session_id) {
            $session = WorkSession::where('session_id', $request->session_id)->first();
            if ($session) {
                return response()->json([
                    'success' => true,
                    'data' => $session,
                    'message' => 'Session restored',
                ]);
            }
        }

        $session = WorkSession::create([
            'user_id' => $userId,
            'product_id' => $validated['product_id'],
            'orientation' => $validated['orientation'] ?? 'horizontal',
            'shape' => $validated['shape'] ?? 'rectangle',
            'selected_attributes' => $validated['selected_attributes'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => $session,
            'message' => 'Session created',
        ]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|string|exists:work_sessions,session_id',
            'orientation' => 'nullable|string',
            'shape' => 'nullable|string',
            'selected_attributes' => 'nullable|array',
            'front_canvas_state' => 'nullable|array',
            'back_canvas_state' => 'nullable|array',
            'front_thumbnail' => 'nullable|string',
            'back_thumbnail' => 'nullable|string',
        ]);

        $session = WorkSession::where('session_id', $validated['session_id'])->firstOrFail();

        $session->update([
            'orientation' => $validated['orientation'] ?? $session->orientation,
            'shape' => $validated['shape'] ?? $session->shape,
            'selected_attributes' => $validated['selected_attributes'] ?? $session->selected_attributes,
            'front_canvas_state' => $validated['front_canvas_state'] ?? $session->front_canvas_state,
            'back_canvas_state' => $validated['back_canvas_state'] ?? $session->back_canvas_state,
            'front_thumbnail' => $validated['front_thumbnail'] ?? $session->front_thumbnail,
            'back_thumbnail' => $validated['back_thumbnail'] ?? $session->back_thumbnail,
        ]);

        return response()->json([
            'success' => true,
            'data' => $session->fresh(),
            'message' => 'Session saved',
        ]);
    }

    public function get(Request $request, $sessionId)
    {
        $session = WorkSession::where('session_id', $sessionId)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function linkDesign(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|string|exists:work_sessions,session_id',
            'design_id' => 'required|exists:user_designs,id',
        ]);

        $session = WorkSession::where('session_id', $validated['session_id'])->firstOrFail();
        $session->update(['design_id' => $validated['design_id']]);

        return response()->json([
            'success' => true,
            'data' => $session->fresh()->load('design'),
        ]);
    }
}
