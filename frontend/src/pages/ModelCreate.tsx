import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import api from '../lib/api';
import {
  PlusIcon,
  TrashIcon,
  ArrowLeftIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  InformationCircleIcon,
} from '@heroicons/react/24/outline';

interface FieldType {
  value: string;
  label: string;
  description: string;
  icon: string;
}

interface Field {
  id: string;
  name: string;
  display_name: string;
  type: string;
  description: string;
  is_required: boolean;
  is_unique: boolean;
  is_indexed: boolean;
  is_searchable: boolean;
  is_filterable: boolean;
  is_sortable: boolean;
  show_in_list: boolean;
  show_in_detail: boolean;
  default_value: string;
  options: string[];
}

const generateId = () => Math.random().toString(36).substr(2, 9);

const defaultField: Omit<Field, 'id'> = {
  name: '',
  display_name: '',
  type: 'string',
  description: '',
  is_required: false,
  is_unique: false,
  is_indexed: false,
  is_searchable: true,
  is_filterable: true,
  is_sortable: true,
  show_in_list: true,
  show_in_detail: true,
  default_value: '',
  options: [],
};

export function ModelCreate() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [fieldTypes, setFieldTypes] = useState<FieldType[]>([]);

  // Model data
  const [name, setName] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [description, setDescription] = useState('');
  const [hasTimestamps, setHasTimestamps] = useState(true);
  const [hasSoftDeletes, setHasSoftDeletes] = useState(false);
  const [generateApi, setGenerateApi] = useState(true);

  // Fields
  const [fields, setFields] = useState<Field[]>([
    { ...defaultField, id: generateId() },
  ]);
  const [expandedField, setExpandedField] = useState<string | null>(null);

  useEffect(() => {
    const fetchFieldTypes = async () => {
      try {
        const response = await api.get('/models/field-types');
        setFieldTypes(response.data);
      } catch (err) {
        console.error('Failed to fetch field types');
      }
    };
    fetchFieldTypes();
  }, []);

  // Auto-generate name from display name
  const handleDisplayNameChange = (value: string) => {
    setDisplayName(value);
    // Convert to PascalCase
    const modelName = value
      .split(/\s+/)
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
      .join('');
    setName(modelName);
  };

  // Auto-generate field name from display name
  const handleFieldDisplayNameChange = (id: string, value: string) => {
    // Convert to snake_case
    const fieldName = value
      .toLowerCase()
      .replace(/\s+/g, '_')
      .replace(/[^a-z0-9_]/g, '');

    setFields(
      fields.map((f) =>
        f.id === id ? { ...f, display_name: value, name: fieldName } : f
      )
    );
  };

  const addField = () => {
    const newField = { ...defaultField, id: generateId() };
    setFields([...fields, newField]);
    setExpandedField(newField.id);
  };

  const removeField = (id: string) => {
    if (fields.length === 1) return;
    setFields(fields.filter((f) => f.id !== id));
  };

  const updateField = (id: string, updates: Partial<Field>) => {
    setFields(fields.map((f) => (f.id === id ? { ...f, ...updates } : f)));
  };

  const moveField = (index: number, direction: 'up' | 'down') => {
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= fields.length) return;

    const newFields = [...fields];
    [newFields[index], newFields[newIndex]] = [
      newFields[newIndex],
      newFields[index],
    ];
    setFields(newFields);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    // Validate
    if (!name || !displayName) {
      setError('Model name is required');
      return;
    }

    if (fields.length === 0 || !fields[0].name) {
      setError('At least one field is required');
      return;
    }

    // Check for empty field names
    const emptyFields = fields.filter((f) => !f.name || !f.display_name);
    if (emptyFields.length > 0) {
      setError('All fields must have a name and display name');
      return;
    }

    setIsLoading(true);

    try {
      const payload = {
        name,
        display_name: displayName,
        description,
        has_timestamps: hasTimestamps,
        has_soft_deletes: hasSoftDeletes,
        generate_api: generateApi,
        fields: fields.map((f) => ({
          name: f.name,
          display_name: f.display_name,
          type: f.type,
          description: f.description,
          is_required: f.is_required,
          is_unique: f.is_unique,
          is_indexed: f.is_indexed,
          is_searchable: f.is_searchable,
          is_filterable: f.is_filterable,
          is_sortable: f.is_sortable,
          show_in_list: f.show_in_list,
          show_in_detail: f.show_in_detail,
          default_value: f.default_value || null,
          options: f.options.length > 0 ? f.options : null,
        })),
      };

      await api.post('/models', payload);
      navigate('/models');
    } catch (err: any) {
      setError(
        err.response?.data?.message ||
          err.response?.data?.errors?.name?.[0] ||
          'Failed to create model'
      );
    } finally {
      setIsLoading(false);
    }
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
                <Link to="/models" className="text-gray-600 hover:text-gray-900">
                  Models
                </Link>
                <span className="text-blue-600 font-medium">Create</span>
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

      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Page Header */}
        <div className="flex items-center gap-4 mb-8">
          <Link
            to="/models"
            className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-200 rounded-lg transition"
          >
            <ArrowLeftIcon className="h-5 w-5" />
          </Link>
          <div>
            <h2 className="text-2xl font-bold text-gray-900">
              Create New Model
            </h2>
            <p className="text-gray-600 mt-1">
              Define your model structure and fields
            </p>
          </div>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Model Settings */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              Model Settings
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label
                  htmlFor="displayName"
                  className="block text-sm font-medium text-gray-700 mb-2"
                >
                  Display Name
                </label>
                <input
                  id="displayName"
                  type="text"
                  value={displayName}
                  onChange={(e) => handleDisplayNameChange(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Blog Post"
                  required
                />
              </div>

              <div>
                <label
                  htmlFor="name"
                  className="block text-sm font-medium text-gray-700 mb-2"
                >
                  Model Name
                </label>
                <input
                  id="name"
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50"
                  placeholder="e.g., BlogPost"
                  required
                  pattern="^[a-zA-Z][a-zA-Z0-9]*$"
                />
                <p className="text-xs text-gray-500 mt-1">
                  PascalCase, no spaces or special characters
                </p>
              </div>

              <div className="md:col-span-2">
                <label
                  htmlFor="description"
                  className="block text-sm font-medium text-gray-700 mb-2"
                >
                  Description (Optional)
                </label>
                <textarea
                  id="description"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Brief description of this model"
                  rows={2}
                />
              </div>
            </div>

            {/* Options */}
            <div className="mt-6 pt-6 border-t">
              <h4 className="text-sm font-medium text-gray-700 mb-3">Options</h4>
              <div className="flex flex-wrap gap-6">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={hasTimestamps}
                    onChange={(e) => setHasTimestamps(e.target.checked)}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <span className="text-sm text-gray-700">Add timestamps</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={hasSoftDeletes}
                    onChange={(e) => setHasSoftDeletes(e.target.checked)}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <span className="text-sm text-gray-700">Soft deletes</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={generateApi}
                    onChange={(e) => setGenerateApi(e.target.checked)}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <span className="text-sm text-gray-700">Generate API</span>
                </label>
              </div>
            </div>
          </div>

          {/* Fields */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Fields</h3>
              <button
                type="button"
                onClick={addField}
                className="flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium text-sm"
              >
                <PlusIcon className="h-4 w-4" />
                Add Field
              </button>
            </div>

            <div className="space-y-4">
              {fields.map((field, index) => (
                <div
                  key={field.id}
                  className="border border-gray-200 rounded-lg overflow-hidden"
                >
                  {/* Field Header */}
                  <div
                    className="flex items-center gap-3 p-4 bg-gray-50 cursor-pointer"
                    onClick={() =>
                      setExpandedField(
                        expandedField === field.id ? null : field.id
                      )
                    }
                  >
                    <div className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          moveField(index, 'up');
                        }}
                        disabled={index === 0}
                        className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                      >
                        <ChevronUpIcon className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          moveField(index, 'down');
                        }}
                        disabled={index === fields.length - 1}
                        className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                      >
                        <ChevronDownIcon className="h-4 w-4" />
                      </button>
                    </div>

                    <div className="flex-1 grid grid-cols-3 gap-4">
                      <input
                        type="text"
                        value={field.display_name}
                        onChange={(e) =>
                          handleFieldDisplayNameChange(field.id, e.target.value)
                        }
                        onClick={(e) => e.stopPropagation()}
                        className="px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                        placeholder="Display Name"
                        required
                      />
                      <input
                        type="text"
                        value={field.name}
                        onChange={(e) =>
                          updateField(field.id, { name: e.target.value })
                        }
                        onClick={(e) => e.stopPropagation()}
                        className="px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-gray-50"
                        placeholder="field_name"
                        required
                        pattern="^[a-z][a-z0-9_]*$"
                      />
                      <select
                        value={field.type}
                        onChange={(e) =>
                          updateField(field.id, { type: e.target.value })
                        }
                        onClick={(e) => e.stopPropagation()}
                        className="px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                      >
                        {fieldTypes.map((type) => (
                          <option key={type.value} value={type.value}>
                            {type.label}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="flex items-center gap-2">
                      {field.is_required && (
                        <span className="text-xs text-red-600 font-medium">
                          Required
                        </span>
                      )}
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          removeField(field.id);
                        }}
                        disabled={fields.length === 1}
                        className="p-1 text-gray-400 hover:text-red-600 disabled:opacity-30"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </button>
                    </div>
                  </div>

                  {/* Field Details (Expanded) */}
                  {expandedField === field.id && (
                    <div className="p-4 border-t space-y-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Description
                        </label>
                        <input
                          type="text"
                          value={field.description}
                          onChange={(e) =>
                            updateField(field.id, {
                              description: e.target.value,
                            })
                          }
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                          placeholder="Optional description"
                        />
                      </div>

                      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={field.is_required}
                            onChange={(e) =>
                              updateField(field.id, {
                                is_required: e.target.checked,
                              })
                            }
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          />
                          <span className="text-sm text-gray-700">Required</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={field.is_unique}
                            onChange={(e) =>
                              updateField(field.id, {
                                is_unique: e.target.checked,
                              })
                            }
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          />
                          <span className="text-sm text-gray-700">Unique</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={field.is_indexed}
                            onChange={(e) =>
                              updateField(field.id, {
                                is_indexed: e.target.checked,
                              })
                            }
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          />
                          <span className="text-sm text-gray-700">Indexed</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={field.is_searchable}
                            onChange={(e) =>
                              updateField(field.id, {
                                is_searchable: e.target.checked,
                              })
                            }
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          />
                          <span className="text-sm text-gray-700">Searchable</span>
                        </label>
                      </div>

                      {(field.type === 'enum' || field.type === 'select') && (
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Options (comma-separated)
                          </label>
                          <input
                            type="text"
                            value={field.options.join(', ')}
                            onChange={(e) =>
                              updateField(field.id, {
                                options: e.target.value
                                  .split(',')
                                  .map((s) => s.trim())
                                  .filter(Boolean),
                              })
                            }
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                            placeholder="option1, option2, option3"
                          />
                        </div>
                      )}

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Default Value
                        </label>
                        <input
                          type="text"
                          value={field.default_value}
                          onChange={(e) =>
                            updateField(field.id, {
                              default_value: e.target.value,
                            })
                          }
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                          placeholder="Optional default value"
                        />
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>

            <button
              type="button"
              onClick={addField}
              className="mt-4 w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 text-gray-600 rounded-lg hover:border-blue-500 hover:text-blue-600 transition"
            >
              <PlusIcon className="h-5 w-5" />
              Add Another Field
            </button>
          </div>

          {/* Info Box */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex gap-3">
            <InformationCircleIcon className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">What happens when you create a model?</p>
              <ul className="list-disc list-inside space-y-1 text-blue-700">
                <li>A database migration is generated and run automatically</li>
                <li>A new database table is created with your defined fields</li>
                <li>REST API endpoints are auto-generated for CRUD operations</li>
              </ul>
            </div>
          </div>

          {/* Submit Button */}
          <div className="flex gap-4">
            <Link
              to="/models"
              className="flex-1 px-6 py-3 text-center text-gray-700 bg-gray-200 rounded-lg font-medium hover:bg-gray-300 transition"
            >
              Cancel
            </Link>
            <button
              type="submit"
              disabled={isLoading}
              className="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg font-medium hover:from-blue-700 hover:to-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? (
                <span className="flex items-center justify-center gap-2">
                  <svg
                    className="animate-spin h-5 w-5"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    ></circle>
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                  </svg>
                  Creating Model...
                </span>
              ) : (
                'Create Model'
              )}
            </button>
          </div>
        </form>
      </main>
    </div>
  );
}
