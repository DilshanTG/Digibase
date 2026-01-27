import { useState, useEffect, useRef } from 'react';
import { Layout } from '../components/Layout';
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
      setFiles(response.data.data || []);
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
    formData.append('is_public', 'true');

    try {
      await api.post('/storage', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      fetchFiles();
      fetchStats();
    } catch {
      setError('Upload failed');
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
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
    if (file.is_image) return 'text-[#3ecf8e] bg-[#3ecf8e]/10';
    if (file.mime_type.startsWith('video/')) return 'text-purple-400 bg-purple-500/10';
    if (file.mime_type.startsWith('audio/')) return 'text-pink-400 bg-pink-500/10';
    if (file.mime_type === 'application/pdf') return 'text-red-400 bg-red-500/10';
    return 'text-blue-400 bg-blue-500/10';
  };

  return (
    <Layout>
      <div className="p-6 lg:p-8 max-w-7xl mx-auto">
        {/* Page Header */}
        <div className="flex justify-between items-center mb-8 animate-slideUp">
          <div>
            <h1 className="text-2xl font-semibold text-[#ededed]">Storage</h1>
            <p className="text-[#a1a1a1] mt-1">Manage your files and assets</p>
          </div>
          <label className="flex items-center gap-2 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md cursor-pointer transition-all duration-200 glow-hover">
            <CloudArrowUpIcon className="w-5 h-5" />
            {isUploading ? 'Uploading...' : 'Upload File'}
            <input
              ref={fileInputRef}
              type="file"
              className="hidden"
              onChange={handleUpload}
              disabled={isUploading}
            />
          </label>
        </div>

        {/* Stats */}
        {stats && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {[
              { label: 'Total Files', value: stats.total_files },
              { label: 'Storage Used', value: stats.total_size_human },
              { label: 'Images', value: stats.by_type?.images?.count || 0 },
              { label: 'Documents', value: stats.by_type?.documents?.count || 0 },
            ].map((stat, i) => (
              <div key={stat.label} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-5 animate-slideUp" style={{ animationDelay: `${i * 50}ms` }}>
                <p className="text-sm text-[#6b6b6b]">{stat.label}</p>
                <p className="text-2xl font-semibold text-[#ededed] mt-1">{stat.value}</p>
              </div>
            ))}
          </div>
        )}

        {/* Toolbar */}
        <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-4 mb-6 animate-slideUp" style={{ animationDelay: '150ms' }}>
          <div className="flex flex-wrap items-center gap-4">
            {/* Search */}
            <div className="flex-1 relative">
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-[#6b6b6b]" />
              <input
                type="text"
                placeholder="Search files..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2 bg-[#323232] border border-[#3a3a3a] text-[#ededed] placeholder-[#6b6b6b] rounded-md focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent"
              />
            </div>

            {/* Type Filter */}
            <select
              value={selectedType}
              onChange={(e) => setSelectedType(e.target.value)}
              className="px-4 py-2 bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md focus:ring-2 focus:ring-[#3ecf8e]"
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
              className="p-2 text-[#a1a1a1] hover:text-white hover:bg-[#323232] rounded-md transition-all duration-200"
            >
              <ArrowPathIcon className={`w-5 h-5 ${isLoading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>

        {/* Error */}
        {error && (
          <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6">
            {error}
          </div>
        )}

        {/* Files Grid */}
        {isLoading ? (
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-4">
                <div className="skeleton aspect-square rounded-lg mb-3" />
                <div className="skeleton h-4 w-3/4 mb-2" />
                <div className="skeleton h-3 w-1/2" />
              </div>
            ))}
          </div>
        ) : files.length === 0 ? (
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-12 text-center animate-slideUp">
            <FolderIcon className="w-16 h-16 text-[#3a3a3a] mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-[#ededed] mb-2">No files yet</h3>
            <p className="text-[#6b6b6b]">Upload your first file to get started</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {files.map((file, index) => {
              const FileIcon = getFileIcon(file);
              return (
                <div
                  key={file.id}
                  className="bg-[#2a2a2a] border border-[#3a3a3a] hover:border-[#4a4a4a] rounded-lg p-4 transition-all duration-200 group animate-slideUp"
                  style={{ animationDelay: `${index * 30}ms` }}
                >
                  {/* Preview */}
                  <div
                    className="aspect-square rounded-lg mb-3 flex items-center justify-center overflow-hidden cursor-pointer bg-[#323232]"
                    onClick={() => setPreviewFile(file)}
                  >
                    {file.is_image ? (
                      <img src={file.url} alt={file.original_name} className="w-full h-full object-cover" />
                    ) : (
                      <div className={`p-6 rounded-lg ${getFileColor(file)}`}>
                        <FileIcon className="w-12 h-12" />
                      </div>
                    )}
                  </div>

                  {/* Info */}
                  <p className="font-medium text-[#ededed] truncate text-sm" title={file.original_name}>
                    {file.original_name}
                  </p>
                  <p className="text-xs text-[#6b6b6b]">{file.human_size}</p>

                  {/* Actions */}
                  <div className="flex items-center gap-1 mt-2 opacity-0 group-hover:opacity-100 transition-all duration-200">
                    <button onClick={() => setPreviewFile(file)} className="p-1.5 text-[#6b6b6b] hover:text-[#3ecf8e] hover:bg-[#3ecf8e]/10 rounded">
                      <EyeIcon className="w-4 h-4" />
                    </button>
                    <a href={file.url} download={file.original_name} className="p-1.5 text-[#6b6b6b] hover:text-blue-400 hover:bg-blue-500/10 rounded">
                      <ArrowDownTrayIcon className="w-4 h-4" />
                    </a>
                    <button onClick={() => { setFileToDelete(file); setDeleteModalOpen(true); }} className="p-1.5 text-[#6b6b6b] hover:text-red-400 hover:bg-red-500/10 rounded">
                      <TrashIcon className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Preview Modal */}
      {previewFile && (
        <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4 animate-fadeIn">
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden animate-slideUp">
            <div className="flex items-center justify-between p-4 border-b border-[#3a3a3a]">
              <h3 className="font-semibold text-[#ededed] truncate">{previewFile.original_name}</h3>
              <button onClick={() => setPreviewFile(null)} className="p-2 text-[#6b6b6b] hover:text-white">
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>
            <div className="p-4 flex items-center justify-center min-h-[300px] max-h-[60vh] overflow-auto bg-[#171717]">
              {previewFile.is_image ? (
                <img src={previewFile.url} alt={previewFile.original_name} className="max-w-full max-h-full" />
              ) : (
                <div className="text-center">
                  <DocumentIcon className="w-24 h-24 text-[#3a3a3a] mx-auto mb-4" />
                  <p className="text-[#6b6b6b]">Preview not available</p>
                </div>
              )}
            </div>
            <div className="p-4 border-t border-[#3a3a3a]">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><span className="text-[#6b6b6b]">Size:</span> <span className="text-[#a1a1a1]">{previewFile.human_size}</span></div>
                <div><span className="text-[#6b6b6b]">Type:</span> <span className="text-[#a1a1a1]">{previewFile.mime_type}</span></div>
                <div><span className="text-[#6b6b6b]">Bucket:</span> <span className="text-[#a1a1a1]">{previewFile.bucket}</span></div>
                <div><span className="text-[#6b6b6b]">Public:</span> <span className="text-[#a1a1a1]">{previewFile.is_public ? 'Yes' : 'No'}</span></div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Delete Modal */}
      {deleteModalOpen && fileToDelete && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 animate-fadeIn">
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-6 max-w-md w-full mx-4 animate-slideUp">
            <h3 className="text-lg font-semibold text-[#ededed] mb-2">Delete File</h3>
            <p className="text-[#a1a1a1] mb-6">
              Are you sure you want to delete "{fileToDelete.original_name}"? This action cannot be undone.
            </p>
            <div className="flex gap-3">
              <button onClick={() => setDeleteModalOpen(false)} className="flex-1 px-4 py-2 text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a]">
                Cancel
              </button>
              <button onClick={handleDelete} className="flex-1 px-4 py-2 text-white bg-red-600 rounded-md hover:bg-red-700">
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </Layout>
  );
}
