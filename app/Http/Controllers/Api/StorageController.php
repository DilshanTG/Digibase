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

        if ($request->has('bucket')) {
            $query->where('bucket', $request->bucket);
        }

        if ($request->has('folder')) {
            $query->where('folder', $request->folder);
        }

        if ($request->has('search')) {
            $query->where('original_name', 'like', '%' . $request->search . '%');
        }

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

    protected array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md',
        'zip', 'rar', 'gz', '7z',
        'mp3', 'wav', 'ogg', 'mp4', 'webm', 'mov', 'avi',
        'json', 'xml',
    ];

    /**
     * Upload a file.
     * ☁️ UNIVERSAL STORAGE ADAPTER: Updated to use 'digibase_storage' disk.
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

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            return response()->json([
                'message' => 'File extension not allowed.',
            ], 422);
        }

        if ($folder) {
            $folder = str_replace(['..', '\\'], '', $folder);
            $folder = trim($folder, '/');
            if (preg_match('/[<>:"|?*]/', $folder)) {
                return response()->json(['message' => 'Invalid folder name.'], 422);
            }
        }

        $filename = Str::uuid() . '.' . $extension;
        $directory = $bucket;
        if ($folder) {
            $directory .= '/' . $folder;
        }

        // ☁️  Use the Unified Disk
        $diskName = 'digibase_storage';
        
        // Ensure visibility is set correctly (public/private) for S3 adapters
        $visibility = $isPublic ? 'public' : 'private';

        $path = Storage::disk($diskName)->putFileAs(
            $directory, 
            $uploadedFile, 
            $filename, 
            ['visibility' => $visibility]
        );

        if (!$path) {
            return response()->json([
                'message' => 'Failed to store file on disk.',
            ], 500);
        }

        $url = Storage::disk($diskName)->url($path);

        $storageFile = StorageFile::create([
            'user_id' => $request->user()->id,
            'name' => $filename,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path' => $path,
            'disk' => $diskName, // Save 'digibase_storage' as the disk
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
                'url' => $url, // Return the resolved URL
                'created_at' => $storageFile->created_at,
            ],
        ], 201);
    }

    public function show(Request $request, StorageFile $file): JsonResponse
    {
        if ($file->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Resolving URL dynamically based on current disk config
        $url = Storage::disk($file->disk)->url($file->path);

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
                'url' => $url,
                'metadata' => $file->metadata,
                'created_at' => $file->created_at,
                'updated_at' => $file->updated_at,
            ],
        ]);
    }

    public function download(Request $request, StorageFile $file): StreamedResponse|JsonResponse
    {
        if ($file->is_public) {
            if (!Storage::disk($file->disk)->exists($file->path)) {
                return response()->json(['message' => 'File not found on disk'], 404);
            }
            return Storage::disk($file->disk)->download($file->path, $file->original_name);
        }

        $authUser = auth('sanctum')->user();
        if (!$authUser) {
            return response()->json(['message' => 'Authentication required', 'error' => 'This file is private.'], 401);
        }

        if ($file->user_id !== $authUser->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if (!Storage::disk($file->disk)->exists($file->path)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

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

        if (isset($validated['is_public']) && $validated['is_public'] !== $file->is_public) {
            $visibility = $validated['is_public'] ? 'public' : 'private';
            Storage::disk($file->disk)->setVisibility($file->path, $visibility);
        }

        $file->update($validated);
        $url = Storage::disk($file->disk)->url($file->path);

        return response()->json([
            'data' => [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
                'bucket' => $file->bucket,
                'folder' => $file->folder,
                'is_public' => $file->is_public,
                'url' => $url,
            ],
        ]);
    }

    public function destroy(Request $request, StorageFile $file): JsonResponse
    {
        if ($file->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

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

    protected function humanFileSize(int|null $bytes): string
    {
        if (!$bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
