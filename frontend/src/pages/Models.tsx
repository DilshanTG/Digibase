import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import api from '../lib/api';
import {
  PlusIcon,
  TableCellsIcon,
  PencilIcon,
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
  const { user, logout } = useAuth();
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
      setModels(response.data);
      setError('');
    } catch (err: any) {
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
    } catch (err: any) {
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
                <Link to="/dashboard" className="text-gray-600 hover:text-gray-900">
                  Dashboard
                </Link>
                <Link to="/models" className="text-blue-600 font-medium">
                  Models
                </Link>
              </nav>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-gray-600">Welcome, {user?.name}</span>
              <button
                onClick={logout}
                className="text-gray-600 hover:text-gray-900 font-medium"
              >
                Logout
              </button>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Page Header */}
        <div className="flex justify-between items-center mb-8">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Models</h2>
            <p className="text-gray-600 mt-1">
              Create and manage your database models
            </p>
          </div>
          <Link
            to="/models/create"
            className="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-2 rounded-lg font-medium hover:from-blue-700 hover:to-purple-700 transition"
          >
            <PlusIcon className="h-5 w-5" />
            Create Model
          </Link>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            {error}
          </div>
        )}

        {/* Loading State */}
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <ArrowPathIcon className="h-8 w-8 text-blue-600 animate-spin" />
          </div>
        ) : models.length === 0 ? (
          /* Empty State */
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <TableCellsIcon className="h-16 w-16 text-gray-400 mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-gray-900 mb-2">
              No models yet
            </h3>
            <p className="text-gray-600 mb-6">
              Get started by creating your first database model
            </p>
            <Link
              to="/models/create"
              className="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg font-medium hover:from-blue-700 hover:to-purple-700 transition"
            >
              <PlusIcon className="h-5 w-5" />
              Create Your First Model
            </Link>
          </div>
        ) : (
          /* Models Grid */
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {models.map((model) => (
              <div
                key={model.id}
                className="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition"
              >
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-blue-100 rounded-lg">
                      <TableCellsIcon className="h-6 w-6 text-blue-600" />
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">
                        {model.display_name}
                      </h3>
                      <p className="text-sm text-gray-500">{model.table_name}</p>
                    </div>
                  </div>
                  <span
                    className={`px-2 py-1 text-xs font-medium rounded-full ${
                      model.is_active
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-800'
                    }`}
                  >
                    {model.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>

                {model.description && (
                  <p className="text-gray-600 text-sm mb-4 line-clamp-2">
                    {model.description}
                  </p>
                )}

                <div className="flex items-center justify-between text-sm text-gray-500 mb-4">
                  <span>{model.fields_count} fields</span>
                  <span>
                    {new Date(model.created_at).toLocaleDateString()}
                  </span>
                </div>

                {/* Field Preview */}
                {model.fields && model.fields.length > 0 && (
                  <div className="border-t pt-4 mb-4">
                    <p className="text-xs text-gray-500 mb-2">Fields:</p>
                    <div className="flex flex-wrap gap-1">
                      {model.fields.slice(0, 4).map((field) => (
                        <span
                          key={field.id}
                          className="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded"
                        >
                          {field.name}
                        </span>
                      ))}
                      {model.fields.length > 4 && (
                        <span className="px-2 py-1 bg-gray-100 text-gray-500 text-xs rounded">
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
                    className="flex-1 flex items-center justify-center gap-1 px-3 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                  >
                    <EyeIcon className="h-4 w-4" />
                    View
                  </Link>
                  <Link
                    to={`/models/${model.id}/edit`}
                    className="flex-1 flex items-center justify-center gap-1 px-3 py-2 text-sm text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 transition"
                  >
                    <PencilIcon className="h-4 w-4" />
                    Edit
                  </Link>
                  <button
                    onClick={() => confirmDelete(model)}
                    className="flex items-center justify-center gap-1 px-3 py-2 text-sm text-red-700 bg-red-100 rounded-lg hover:bg-red-200 transition"
                  >
                    <TrashIcon className="h-4 w-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </main>

      {/* Delete Confirmation Modal */}
      {deleteModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-2">
              Delete Model
            </h3>
            <p className="text-gray-600 mb-6">
              Are you sure you want to delete "{modelToDelete?.display_name}"?
              This will also delete the database table and all its data. This
              action cannot be undone.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setDeleteModalOpen(false)}
                className="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                disabled={isDeleting}
              >
                Cancel
              </button>
              <button
                onClick={handleDelete}
                disabled={isDeleting}
                className="flex-1 px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700 transition disabled:opacity-50"
              >
                {isDeleting ? 'Deleting...' : 'Delete'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
