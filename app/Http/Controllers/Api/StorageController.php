<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    /**
     * List all files for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StorageFile::where('user_id', $request->user()->id);

        // Filter by bucket
        if ($request->has('bucket')) {
            $query->where('bucket', $request->bucket);
        }

        // Filter by folder
        if ($request->has('folder')) {
            $query->where('folder', $request->folder);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('original_name', 'like', '%' . $request->search . '%');
        }

        // Filter by type
        if ($request->has('type')) {
            switch ($request->type) {
                case 'image':
                    $query->where('mime_type', 'like', 'image/%');
                    break;
                case 'document':
                    $query->whereIn('mime_type', [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                        'text/csv',
                    ]);
                    break;
                case 'video':
                    $query->where('mime_type', 'like', 'video/%');
                    break;
                case 'audio':
                    $query->where('mime_type', 'like', 'audio/%');
                    break;
            }
        }

        $files = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $files->map(function ($file) {
                return [
                    'id' => $file->id,
                    'name' => $file->name,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                    'human_size' => $file->human_size,
                    'bucket' => $file->bucket,
                    'folder' => $file->folder,
                    'is_public' => $file->is_public,
                    'is_image' => $file->is_image,
                    'extension' => $file->extension,
                    'url' => $file->url,
                    'created_at' => $file->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Dangerous file extensions that should never be uploaded.
     */
    protected array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'zsh',
        'js', 'mjs', 'vbs', 'vbe', 'wsf', 'wsh',
        'jar', 'jsp', 'jspx', 'asp', 'aspx', 'cer', 'csr',
        'htaccess', 'htpasswd', 'ini', 'config',
    ];

    /**
     * Allowed MIME types for upload.
     */
    protected array $allowedMimeTypes = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp', 'image/tiff',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'text/markdown',
        // Archives
        'application/zip', 'application/x-rar-compressed', 'application/gzip', 'application/x-7z-compressed',
        // Audio
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/webm',
        // Video
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo',
        // Other
        'application/json', 'application/xml', 'text/xml', 'application/octet-stream',
        'image/x-icon', 'image/vnd.microsoft.icon', 'application/zip', 'application/x-zip-compressed'
    ];

    /**
     * Upload a file.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'bucket' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'folder' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_\/-]+$/',
            'is_public' => 'nullable|boolean',
        ]);

        $uploadedFile = $request->file('file');
        $bucket = $request->get('bucket', 'default');
        $folder = $request->get('folder');
        $isPublic = $request->boolean('is_public', false);

        // Validate file extension
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        if (in_array($extension, $this->dangerousExtensions)) {
            return response()->json([
                'message' => 'File type not allowed for security reasons.',
            ], 422);
        }

        // Validate MIME type
        $mimeType = $uploadedFile->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return response()->json([
                'message' => 'File type not allowed. Supported types: images, documents, audio, video, and archives.',
            ], 422);
        }

        // Sanitize folder to prevent path traversal
        if ($folder) {
            // Remove any path traversal attempts
            $folder = str_replace(['..', '\\'], '', $folder);
            // Remove leading/trailing slashes and normalize
            $folder = trim($folder, '/');
            // Validate folder doesn't contain suspicious patterns
            if (preg_match('/[<>:"|?*]/', $folder)) {
                return response()->json([
                    'message' => 'Invalid folder name.',
                ], 422);
            }
        }

        // Generate unique filename with sanitized extension
        $filename = Str::uuid() . '.' . $extension;

        // Build directory path
        $directory = $bucket;
        if ($folder) {
            $directory .= '/' . $folder;
        }

        // Store file using Laravel's file handling
        $disk = $isPublic ? 'public' : 'local';
        $fullPath = Storage::disk($disk)->putFileAs($directory, $uploadedFile, $filename);

        if (!$fullPath) {
            return response()->json([
                'message' => 'Failed to store file on disk.',
            ], 500);
        }

        // Create record
        $storageFile = StorageFile::create([
            'user_id' => $request->user()->id,
            'name' => $filename,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path' => $fullPath,
            'disk' => $disk,
            'mime_type' => $uploadedFile->getMimeType(),
            'size' => $uploadedFile->getSize(),
            'bucket' => $bucket,
            'folder' => $folder,
            'is_public' => $isPublic,
        ]);

        return response()->json([
            'data' => [
                'id' => $storageFile->id,
                'name' => $storageFile->name,
                'original_name' => $storageFile->original_name,
                'mime_type' => $storageFile->mime_type,
                'size' => $storageFile->size,
                'human_size' => $storageFile->human_size,
                'bucket' => $storageFile->bucket,
                'folder' => $storageFile->folder,
                'is_public' => $storageFile->is_public,
                'is_image' => $storageFile->is_image,
                'extension' => $storageFile->extension,
                'url' => $storageFile->url,
                'created_at' => $storageFile->created_at,
            ],
        ], 201);
    }

    /**
     * Get file details.
     */
    public function show(Request $request, StorageFile $file): JsonResponse
    {
        if ($file->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'human_size' => $file->human_size,
                'bucket' => $file->bucket,
                'folder' => $file->folder,
                'is_public' => $file->is_public,
                'is_image' => $file->is_image,
                'extension' => $file->extension,
                'url' => $file->url,
                'metadata' => $file->metadata,
                'created_at' => $file->created_at,
                'updated_at' => $file->updated_at,
            ],
        ]);
    }

    /**
     * Download a file.
     */
    public function download(Request $request, StorageFile $file): StreamedResponse|JsonResponse
    {
        // Allow download if public or owned by user
        if (!$file->is_public && $file->user_id !== $request->user()?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Storage::disk($file->disk)->exists($file->path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    /**
     * Update file metadata.
     */
    public function update(Request $request, StorageFile $file): JsonResponse
    {
        if ($file->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'bucket' => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:255',
            'is_public' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        // Handle visibility change
        if (isset($validated['is_public']) && $validated['is_public'] !== $file->is_public) {
            $newDisk = $validated['is_public'] ? 'public' : 'local';
            $oldDisk = $file->disk;

            // Move file to new disk
            if ($newDisk !== $oldDisk) {
                $content = Storage::disk($oldDisk)->get($file->path);
                Storage::disk($newDisk)->put($file->path, $content);
                Storage::disk($oldDisk)->delete($file->path);
                $validated['disk'] = $newDisk;
            }
        }

        $file->update($validated);

        return response()->json([
            'data' => [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
                'bucket' => $file->bucket,
                'folder' => $file->folder,
                'is_public' => $file->is_public,
                'url' => $file->url,
            ],
        ]);
    }

    /**
     * Delete a file.
     */
    public function destroy(Request $request, StorageFile $file): JsonResponse
    {
        if ($file->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete from storage
        Storage::disk($file->disk)->delete($file->path);

        // Delete record
        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * Get storage stats for user.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalFiles = StorageFile::where('user_id', $userId)->count();
        $totalSize = StorageFile::where('user_id', $userId)->sum('size');

        $byType = StorageFile::where('user_id', $userId)
            ->selectRaw("
                CASE
                    WHEN mime_type LIKE 'image/%' THEN 'images'
                    WHEN mime_type LIKE 'video/%' THEN 'videos'
                    WHEN mime_type LIKE 'audio/%' THEN 'audio'
                    WHEN mime_type IN ('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain') THEN 'documents'
                    ELSE 'other'
                END as type,
                COUNT(*) as count,
                SUM(size) as size
            ")
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $byBucket = StorageFile::where('user_id', $userId)
            ->selectRaw('bucket, COUNT(*) as count, SUM(size) as size')
            ->groupBy('bucket')
            ->get();

        return response()->json([
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_human' => $this->humanFileSize($totalSize),
            'by_type' => $byType,
            'by_bucket' => $byBucket,
        ]);
    }

    /**
     * List buckets for user.
     */
    public function buckets(Request $request): JsonResponse
    {
        $buckets = StorageFile::where('user_id', $request->user()->id)
            ->selectRaw('bucket, COUNT(*) as files_count, SUM(size) as total_size')
            ->groupBy('bucket')
            ->get()
            ->map(function ($bucket) {
                return [
                    'name' => $bucket->bucket,
                    'files_count' => $bucket->files_count,
                    'total_size' => $bucket->total_size,
                    'total_size_human' => $this->humanFileSize($bucket->total_size),
                ];
            });

        return response()->json(['data' => $buckets]);
    }

    /**
     * Convert bytes to human readable format.
     */
    protected function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
