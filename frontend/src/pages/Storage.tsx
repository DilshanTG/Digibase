import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import api from '../lib/api';
import {
  CloudArrowUpIcon,
  FolderIcon,
  DocumentIcon,
  PhotoIcon,
  FilmIcon,
  MusicalNoteIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  EyeIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';

interface StorageFile {
  id: number;
  name: string;
  original_name: string;
  mime_type: string;
  size: number;
  human_size: string;
  bucket: string;
  folder: string | null;
  is_public: boolean;
  is_image: boolean;
  extension: string;
  url: string;
  created_at: string;
}

interface StorageStats {
  total_files: number;
  total_size: number;
  total_size_human: string;
  by_type: Record<string, { count: number; size: number }>;
}

export function Storage() {
  const { user, logout } = useAuth();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [files, setFiles] = useState<StorageFile[]>([]);
  const [stats, setStats] = useState<StorageStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [selectedBucket] = useState('');
  const [selectedType, setSelectedType] = useState('');
  const [previewFile, setPreviewFile] = useState<StorageFile | null>(null);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [fileToDelete, setFileToDelete] = useState<StorageFile | null>(null);

  const fetchFiles = async () => {
    try {
      setIsLoading(true);
      const params = new URLSearchParams();
      if (search) params.append('search', search);
      if (selectedBucket) params.append('bucket', selectedBucket);
      if (selectedType) params.append('type', selectedType);

      const response = await api.get(`/storage?${params.toString()}`);
      setFiles(response.data.data);
    } catch {
      setError('Failed to load files');
    } finally {
      setIsLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const response = await api.get('/storage/stats');
      setStats(response.data);
    } catch {
      // Stats are optional
    }
  };

  useEffect(() => {
    fetchFiles();
    fetchStats();
  }, [search, selectedBucket, selectedType]);

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    setError('');

    const formData = new FormData();
    formData.append('file', file);
    formData.append('bucket', selectedBucket || 'default');
    formData.append('is_public', 'false');

    try {
      await api.post('/storage', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      fetchFiles();
      fetchStats();
    } catch (err: unknown) {
      const errorMessage = err instanceof Error ? err.message : 'Upload failed';
      setError(errorMessage);
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleDelete = async () => {
    if (!fileToDelete) return;

    try {
      await api.delete(`/storage/${fileToDelete.id}`);
      setFiles(files.filter((f) => f.id !== fileToDelete.id));
      setDeleteModalOpen(false);
      setFileToDelete(null);
      fetchStats();
    } catch {
      setError('Failed to delete file');
    }
  };

  const getFileIcon = (file: StorageFile) => {
    if (file.is_image) return PhotoIcon;
    if (file.mime_type.startsWith('video/')) return FilmIcon;
    if (file.mime_type.startsWith('audio/')) return MusicalNoteIcon;
    return DocumentIcon;
  };

  const getFileColor = (file: StorageFile) => {
    if (file.is_image) return 'text-green-600 bg-green-100';
    if (file.mime_type.startsWith('video/')) return 'text-purple-600 bg-purple-100';
    if (file.mime_type.startsWith('audio/')) return 'text-pink-600 bg-pink-100';
    if (file.mime_type === 'application/pdf') return 'text-red-600 bg-red-100';
    return 'text-blue-600 bg-blue-100';
  };

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center py-4">
            <div className="flex items-center gap-6">
              <Link to="/dashboard">
                <h1 className="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                  Digibase
                </h1>
              </Link>
              <nav className="flex items-center gap-4">
                <Link to="/dashboard" className="text-gray-600 hover:text-gray-900">Dashboard</Link>
                <Link to="/models" className="text-gray-600 hover:text-gray-900">Models</Link>
                <span className="text-blue-600 font-medium">Storage</span>
              </nav>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-gray-600">Welcome, {user?.name}</span>
              <button onClick={logout} className="text-gray-600 hover:text-gray-900 font-medium">Logout</button>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Stats */}
        {stats && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div className="bg-white rounded-xl shadow-sm p-6">
              <p className="text-sm text-gray-500">Total Files</p>
              <p className="text-2xl font-bold text-gray-900">{stats.total_files}</p>
            </div>
            <div className="bg-white rounded-xl shadow-sm p-6">
              <p className="text-sm text-gray-500">Storage Used</p>
              <p className="text-2xl font-bold text-gray-900">{stats.total_size_human}</p>
            </div>
            <div className="bg-white rounded-xl shadow-sm p-6">
              <p className="text-sm text-gray-500">Images</p>
              <p className="text-2xl font-bold text-gray-900">{stats.by_type?.images?.count || 0}</p>
            </div>
            <div className="bg-white rounded-xl shadow-sm p-6">
              <p className="text-sm text-gray-500">Documents</p>
              <p className="text-2xl font-bold text-gray-900">{stats.by_type?.documents?.count || 0}</p>
            </div>
          </div>
        )}

        {/* Toolbar */}
        <div className="bg-white rounded-xl shadow-sm p-4 mb-6">
          <div className="flex flex-wrap items-center gap-4">
            {/* Upload Button */}
            <label className="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-2 rounded-lg font-medium cursor-pointer hover:from-blue-700 hover:to-purple-700 transition">
              <CloudArrowUpIcon className="h-5 w-5" />
              {isUploading ? 'Uploading...' : 'Upload File'}
              <input
                ref={fileInputRef}
                type="file"
                className="hidden"
                onChange={handleUpload}
                disabled={isUploading}
              />
            </label>

            {/* Search */}
            <div className="flex-1 relative">
              <MagnifyingGlassIcon className="h-5 w-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                placeholder="Search files..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            {/* Type Filter */}
            <select
              value={selectedType}
              onChange={(e) => setSelectedType(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Types</option>
              <option value="image">Images</option>
              <option value="document">Documents</option>
              <option value="video">Videos</option>
              <option value="audio">Audio</option>
            </select>

            {/* Refresh */}
            <button
              onClick={() => { fetchFiles(); fetchStats(); }}
              className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg"
            >
              <ArrowPathIcon className={`h-5 w-5 ${isLoading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>

        {/* Error */}
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            {error}
          </div>
        )}

        {/* Files Grid */}
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <ArrowPathIcon className="h-8 w-8 text-blue-600 animate-spin" />
          </div>
        ) : files.length === 0 ? (
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <FolderIcon className="h-16 w-16 text-gray-400 mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-gray-900 mb-2">No files yet</h3>
            <p className="text-gray-600 mb-6">Upload your first file to get started</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {files.map((file) => {
              const FileIcon = getFileIcon(file);
              return (
                <div
                  key={file.id}
                  className="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition group"
                >
                  {/* Preview */}
                  <div
                    className="aspect-square rounded-lg mb-3 flex items-center justify-center overflow-hidden cursor-pointer"
                    onClick={() => setPreviewFile(file)}
                  >
                    {file.is_image ? (
                      <img
                        src={file.url}
                        alt={file.original_name}
                        className="w-full h-full object-cover"
                        onError={(e) => {
                          (e.target as HTMLImageElement).style.display = 'none';
                        }}
                      />
                    ) : (
                      <div className={`p-6 rounded-lg ${getFileColor(file)}`}>
                        <FileIcon className="h-12 w-12" />
                      </div>
                    )}
                  </div>

                  {/* Info */}
                  <p className="font-medium text-gray-900 truncate text-sm" title={file.original_name}>
                    {file.original_name}
                  </p>
                  <p className="text-xs text-gray-500">{file.human_size}</p>

                  {/* Actions */}
                  <div className="flex items-center gap-1 mt-2 opacity-0 group-hover:opacity-100 transition">
                    <button
                      onClick={() => setPreviewFile(file)}
                      className="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded"
                      title="Preview"
                    >
                      <EyeIcon className="h-4 w-4" />
                    </button>
                    <a
                      href={file.url}
                      download={file.original_name}
                      className="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded"
                      title="Download"
                    >
                      <ArrowDownTrayIcon className="h-4 w-4" />
                    </a>
                    <button
                      onClick={() => { setFileToDelete(file); setDeleteModalOpen(true); }}
                      className="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded"
                      title="Delete"
                    >
                      <TrashIcon className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </main>

      {/* Preview Modal */}
      {previewFile && (
        <div className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div className="flex items-center justify-between p-4 border-b">
              <h3 className="font-semibold text-gray-900 truncate">{previewFile.original_name}</h3>
              <button
                onClick={() => setPreviewFile(null)}
                className="p-2 text-gray-500 hover:text-gray-700"
              >
                <XMarkIcon className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4 flex items-center justify-center min-h-[300px] max-h-[60vh] overflow-auto">
              {previewFile.is_image ? (
                <img src={previewFile.url} alt={previewFile.original_name} className="max-w-full max-h-full" />
              ) : (
                <div className="text-center">
                  <DocumentIcon className="h-24 w-24 text-gray-400 mx-auto mb-4" />
                  <p className="text-gray-600">Preview not available</p>
                </div>
              )}
            </div>
            <div className="p-4 border-t bg-gray-50">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><span className="text-gray-500">Size:</span> {previewFile.human_size}</div>
                <div><span className="text-gray-500">Type:</span> {previewFile.mime_type}</div>
                <div><span className="text-gray-500">Bucket:</span> {previewFile.bucket}</div>
                <div><span className="text-gray-500">Public:</span> {previewFile.is_public ? 'Yes' : 'No'}</div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Delete Modal */}
      {deleteModalOpen && fileToDelete && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-2">Delete File</h3>
            <p className="text-gray-600 mb-6">
              Are you sure you want to delete "{fileToDelete.original_name}"? This action cannot be undone.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setDeleteModalOpen(false)}
                className="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
              >
                Cancel
              </button>
              <button
                onClick={handleDelete}
                className="flex-1 px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
