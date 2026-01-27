import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
  PlusIcon,
  TableCellsIcon,
  TrashIcon,
  EyeIcon,
  ArrowPathIcon,
} from '@heroicons/react/24/outline';

interface DynamicField {
  id: number;
  name: string;
  display_name: string;
  type: string;
}

interface DynamicModel {
  id: number;
  name: string;
  table_name: string;
  display_name: string;
  description: string | null;
  icon: string;
  is_active: boolean;
  fields_count: number;
  fields: DynamicField[];
  created_at: string;
}

export function Models() {
  const [models, setModels] = useState<DynamicModel[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [modelToDelete, setModelToDelete] = useState<DynamicModel | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const fetchModels = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/models');
      setModels(response.data.data || response.data || []);
      setError('');
    } catch {
      setError('Failed to load models');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchModels();
  }, []);

  const handleDelete = async () => {
    if (!modelToDelete) return;

    setIsDeleting(true);
    try {
      await api.delete(`/models/${modelToDelete.id}`);
      setModels(models.filter((m) => m.id !== modelToDelete.id));
      setDeleteModalOpen(false);
      setModelToDelete(null);
    } catch {
      setError('Failed to delete model');
    } finally {
      setIsDeleting(false);
    }
  };

  const confirmDelete = (model: DynamicModel) => {
    setModelToDelete(model);
    setDeleteModalOpen(true);
  };

  return (
    <Layout>
      <div className="p-6 lg:p-8 max-w-7xl mx-auto">
        {/* Page Header */}
        <div className="flex justify-between items-center mb-8 animate-slideUp">
          <div>
            <h1 className="text-2xl font-semibold text-[#ededed]">Models</h1>
            <p className="text-[#a1a1a1] mt-1">Create and manage your database models</p>
          </div>
          <Link
            to="/models/create"
            className="flex items-center gap-2 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md transition-all duration-200 glow-hover"
          >
            <PlusIcon className="w-4 h-4" />
            Create Model
          </Link>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 animate-fadeIn">
            {error}
          </div>
        )}

        {/* Loading State */}
        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-6">
                <div className="skeleton h-6 w-32 mb-4" />
                <div className="skeleton h-4 w-24 mb-4" />
                <div className="skeleton h-4 w-full" />
              </div>
            ))}
          </div>
        ) : models.length === 0 ? (
          /* Empty State */
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-12 text-center animate-slideUp">
            <TableCellsIcon className="w-16 h-16 text-[#3a3a3a] mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-[#ededed] mb-2">No models yet</h3>
            <p className="text-[#6b6b6b] mb-6">Get started by creating your first database model</p>
            <Link
              to="/models/create"
              className="inline-flex items-center gap-2 px-6 py-3 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md transition-all duration-200"
            >
              <PlusIcon className="w-5 h-5" />
              Create Your First Model
            </Link>
          </div>
        ) : (
          /* Models Grid */
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {models.map((model, index) => (
              <div
                key={model.id}
                className="bg-[#2a2a2a] border border-[#3a3a3a] hover:border-[#4a4a4a] rounded-lg p-6 transition-all duration-200 animate-slideUp"
                style={{ animationDelay: `${index * 50}ms` }}
              >
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#3ecf8e]/20 to-[#24b47e]/20 flex items-center justify-center">
                      <TableCellsIcon className="w-5 h-5 text-[#3ecf8e]" />
                    </div>
                    <div>
                      <h3 className="font-medium text-[#ededed]">{model.display_name}</h3>
                      <p className="text-sm text-[#6b6b6b]">{model.table_name}</p>
                    </div>
                  </div>
                  <span
                    className={`px-2 py-1 text-xs font-medium rounded ${
                      model.is_active
                        ? 'bg-[#3ecf8e]/10 text-[#3ecf8e]'
                        : 'bg-[#3a3a3a] text-[#6b6b6b]'
                    }`}
                  >
                    {model.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>

                {model.description && (
                  <p className="text-[#a1a1a1] text-sm mb-4 line-clamp-2">{model.description}</p>
                )}

                <div className="flex items-center justify-between text-sm text-[#6b6b6b] mb-4">
                  <span>{model.fields_count || model.fields?.length || 0} fields</span>
                  <span>{new Date(model.created_at).toLocaleDateString()}</span>
                </div>

                {/* Field Preview */}
                {model.fields && model.fields.length > 0 && (
                  <div className="border-t border-[#3a3a3a] pt-4 mb-4">
                    <p className="text-xs text-[#6b6b6b] mb-2">Fields:</p>
                    <div className="flex flex-wrap gap-1">
                      {model.fields.slice(0, 4).map((field) => (
                        <span
                          key={field.id}
                          className="px-2 py-1 bg-[#323232] text-[#a1a1a1] text-xs rounded"
                        >
                          {field.name}
                        </span>
                      ))}
                      {model.fields.length > 4 && (
                        <span className="px-2 py-1 bg-[#323232] text-[#6b6b6b] text-xs rounded">
                          +{model.fields.length - 4} more
                        </span>
                      )}
                    </div>
                  </div>
                )}

                {/* Actions */}
                <div className="flex items-center gap-2">
                  <Link
                    to={`/models/${model.id}`}
                    className="flex-1 flex items-center justify-center gap-1 px-3 py-2 text-sm text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a] hover:text-white transition-all duration-200"
                  >
                    <EyeIcon className="w-4 h-4" />
                    View
                  </Link>
                  <button
                    onClick={() => confirmDelete(model)}
                    className="flex items-center justify-center gap-1 px-3 py-2 text-sm text-red-400 bg-red-500/10 rounded-md hover:bg-red-500/20 transition-all duration-200"
                  >
                    <TrashIcon className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Delete Confirmation Modal */}
      {deleteModalOpen && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 animate-fadeIn">
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-6 max-w-md w-full mx-4 animate-slideUp">
            <h3 className="text-lg font-semibold text-[#ededed] mb-2">Delete Model</h3>
            <p className="text-[#a1a1a1] mb-6">
              Are you sure you want to delete "{modelToDelete?.display_name}"? This will also delete
              the database table and all its data. This action cannot be undone.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setDeleteModalOpen(false)}
                className="flex-1 px-4 py-2 text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a] transition-all duration-200"
                disabled={isDeleting}
              >
                Cancel
              </button>
              <button
                onClick={handleDelete}
                disabled={isDeleting}
                className="flex-1 px-4 py-2 text-white bg-red-600 rounded-md hover:bg-red-700 transition-all duration-200 disabled:opacity-50"
              >
                {isDeleting ? (
                  <span className="flex items-center justify-center gap-2">
                    <ArrowPathIcon className="w-4 h-4 animate-spin" />
                    Deleting...
                  </span>
                ) : (
                  'Delete'
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </Layout>
  );
}
