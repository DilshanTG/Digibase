<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Media Upload API Controller
 *
 * Handles file uploads via API with public URL generation.
 * Supports configurable folder structure and file type validation.
 *
 * @package App\Http\Controllers\Api
 */
class MediaController extends Controller
{
    /**
     * Upload a file and return its public URL.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Request Parameters:
     * - file (required): The file to upload (max 10MB)
     * - folder (optional): Subfolder name within uploads directory
     *
     * Response:
     * {
     *   "message": "File uploaded successfully",
     *   "url": "https://domain.com/storage/uploads/filename.jpg",
     *   "path": "uploads/filename.jpg",
     *   "type": "image/jpeg",
     *   "size": 102400,
     *   "original_name": "photo.jpg"
     * }
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'folder' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$request->hasFile('file')) {
            return response()->json([
                'message' => 'No file uploaded',
                'errors' => ['file' => ['File is required']]
            ], 400);
        }

        try {
            $file = $request->file('file');
            $folder = $request->input('folder', 'uploads');

            // Sanitize folder name
            $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder);

            // Store file publicly
            $path = $file->store($folder, 'public');

            if (!$path) {
                throw new \Exception('Failed to store file');
            }

            $url = Storage::url($path);

            Log::info('File uploaded via API', [
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'original' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'message' => 'File uploaded successfully',
                'url' => url($url), // Full absolute URL
                'path' => $path,
                'type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'File upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a file from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Request Parameters:
     * - path (required): The file path to delete (e.g., uploads/filename.jpg)
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->input('path');

            // Security: Ensure path doesn't contain directory traversal
            if (str_contains($path, '..')) {
                return response()->json([
                    'message' => 'Invalid path',
                    'errors' => ['path' => ['Path contains invalid characters']]
                ], 400);
            }

            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'message' => 'File not found',
                    'errors' => ['path' => ['File does not exist']]
                ], 404);
            }

            Storage::disk('public')->delete($path);

            Log::info('File deleted via API', [
                'path' => $path,
            ]);

            return response()->json([
                'message' => 'File deleted successfully',
                'path' => $path,
            ], 200);

        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'File deletion failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
