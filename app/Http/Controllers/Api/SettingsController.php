<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = Setting::all()->groupBy('group');
        return response()->json($settings);
    }

    /**
     * Get settings for a specific group.
     */
    public function show(Request $request, $group): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = Setting::where('group', $group)->get();
        return response()->json($settings);
    }

    /**
     * Update multiple settings.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->settings as $item) {
                Setting::updateOrCreate(
                    ['key' => $item['key']],
                    ['value' => $item['value']]
                );
            }
            DB::commit();
            return response()->json(['message' => 'Settings updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update settings: ' . $e->getMessage()], 500);
        }
    }
}
