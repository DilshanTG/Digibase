import { useState, useEffect } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
  ArrowLeftIcon,
  TableCellsIcon,
  CodeBracketIcon,
  DocumentDuplicateIcon,
  CheckIcon,
  PlayIcon,
  ArrowPathIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';

interface DynamicField {
  id: number;
  name: string;
  display_name: string;
  type: string;
  description: string | null;
  is_required: boolean;
  is_unique: boolean;
  is_searchable: boolean;
  is_filterable: boolean;
  is_sortable: boolean;
  show_in_list: boolean;
  show_in_detail: boolean;
  default_value: string | null;
  options: string[] | null;
}

interface DynamicModel {
  id: number;
  name: string;
  table_name: string;
  display_name: string;
  description: string | null;
  icon: string;
  is_active: boolean;
  has_timestamps: boolean;
  has_soft_deletes: boolean;
  generate_api: boolean;
  fields: DynamicField[];
  created_at: string;
}

type TabType = 'overview' | 'api' | 'snippets' | 'data';
type ModalMode = 'create' | 'edit' | 'delete';

export function ModelDetail() {
  const { id } = useParams<{ id: string }>();
  const [model, setModel] = useState<DynamicModel | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [copiedSnippet, setCopiedSnippet] = useState<string | null>(null);

  // Data tab state
  const [records, setRecords] = useState<Record<string, unknown>[]>([]);
  const [isLoadingData, setIsLoadingData] = useState(false);
  const [dataMeta, setDataMeta] = useState({ total: 0, current_page: 1, last_page: 1 });

  // CRUD Modal state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<ModalMode>('create');
  const [currentRecord, setCurrentRecord] = useState<Record<string, any> | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState('');

  // Add field state
  const [isAddFieldModalOpen, setIsAddFieldModalOpen] = useState(false);
  const [newFields, setNewFields] = useState<any[]>([]);
  const [isAddingFields, setIsAddingFields] = useState(false);

  useEffect(() => {
    const fetchModel = async () => {
      try {
        setIsLoading(true);
        const response = await api.get(`/models/${id}`);
        setModel(response.data);
        setError('');
      } catch {
        setError('Failed to load model');
      } finally {
        setIsLoading(false);
      }
    };
    fetchModel();
  }, [id]);

  const fetchData = async () => {
    if (!model) return;
    try {
      setIsLoadingData(true);
      const response = await api.get(`/data/${model.table_name}`);
      setRecords(response.data.data || []);
      setDataMeta(response.data.meta || { total: 0, current_page: 1, last_page: 1 });
    } catch {
      setRecords([]);
    } finally {
      setIsLoadingData(false);
    }
  };

  const [fieldTypes, setFieldTypes] = useState<any[]>([]);

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

  useEffect(() => {
    if (activeTab === 'data' && model) {
      fetchData();
    }
  }, [activeTab, model]);

  const handleOpenModal = (mode: ModalMode, record?: Record<string, any>) => {
    setModalMode(mode);
    setCurrentRecord(record || null);
    setFormError('');
    setIsModalOpen(true);
  };

  const handleOpenAddFieldModal = () => {
    setNewFields([{
      name: '',
      display_name: '',
      type: 'string',
      is_required: false,
      is_unique: false,
      is_searchable: true,
      is_filterable: true,
      is_sortable: true,
      show_in_list: true,
      show_in_detail: true,
    }]);
    setIsAddFieldModalOpen(true);
  };

  const handleAddNewFieldRow = () => {
    setNewFields([...newFields, {
      name: '',
      display_name: '',
      type: 'string',
      is_required: false,
      is_unique: false,
      is_searchable: true,
      is_filterable: true,
      is_sortable: true,
      show_in_list: true,
      show_in_detail: true,
    }]);
  };

  const handleRemoveNewFieldRow = (index: number) => {
    setNewFields(newFields.filter((_, i) => i !== index));
  };

  const handleUpdateNewField = (index: number, updates: any) => {
    const updated = [...newFields];
    updated[index] = { ...updated[index], ...updates };

    // Auto-generate name from display_name if name is empty or matched previous display_name
    if (updates.display_name !== undefined) {
      const snakeName = updates.display_name.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
      updated[index].name = snakeName;
    }

    setNewFields(updated);
  };

  const handleSubmitNewFields = async () => {
    if (!model) return;
    setIsAddingFields(true);
    setFormError('');
    try {
      await api.post(`/models/${model.id}/fields`, { fields: newFields });

      // Refresh model
      const response = await api.get(`/models/${id}`);
      setModel(response.data);

      setIsAddFieldModalOpen(false);
    } catch (err: any) {
      setFormError(err.response?.data?.message || 'Failed to add fields');
    } finally {
      setIsAddingFields(false);
    }
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setCurrentRecord(null);
    setFormError('');
  };

  const handleSubmitRecord = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!model) return;

    setIsSubmitting(true);
    setFormError('');

    try {
      const formData = new FormData(e.currentTarget);
      const data: Record<string, any> = {};

      model.fields.forEach((field) => {
        const value = formData.get(field.name);
        if (field.type === 'boolean') {
          data[field.name] = value === 'on';
        } else if (field.type === 'integer' || field.type === 'number') {
          data[field.name] = value ? Number(value) : null;
        } else {
          data[field.name] = value;
        }
      });

      if (modalMode === 'create') {
        await api.post(`/data/${model.table_name}`, data);
      } else if (modalMode === 'edit' && currentRecord) {
        await api.put(`/data/${model.table_name}/${currentRecord.id}`, data);
      }

      await fetchData();
      handleCloseModal();
    } catch (err: any) {
      setFormError(err.response?.data?.message || 'Failed to save record');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteRecord = async () => {
    if (!model || !currentRecord) return;

    setIsSubmitting(true);
    setFormError('');

    try {
      await api.delete(`/data/${model.table_name}/${currentRecord.id}`);
      await fetchData();
      handleCloseModal();
    } catch (err: any) {
      setFormError(err.response?.data?.message || 'Failed to delete record');
    } finally {
      setIsSubmitting(false);
    }
  };

  const copyToClipboard = async (text: string, snippetId: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedSnippet(snippetId);
    setTimeout(() => setCopiedSnippet(null), 2000);
  };

  const getBaseUrl = () => window.location.origin.replace(':5173', ':8000');

  const generateCurlSnippets = () => {
    if (!model) return {};
    const baseUrl = getBaseUrl();
    const tableName = model.table_name;

    const sampleData = model.fields.reduce((acc, field) => {
      switch (field.type) {
        case 'string':
        case 'text':
          acc[field.name] = `"Sample ${field.display_name}"`;
          break;
        case 'email':
          acc[field.name] = '"user@example.com"';
          break;
        case 'integer':
          acc[field.name] = '1';
          break;
        case 'boolean':
          acc[field.name] = 'true';
          break;
        default:
          acc[field.name] = `"value"`;
      }
      return acc;
    }, {} as Record<string, string>);

    const dataString = Object.entries(sampleData)
      .map(([key, value]) => `"${key}": ${value}`)
      .join(', ');

    return {
      list: `curl -X GET "${baseUrl}/api/data/${tableName}" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Accept: application/json"`,
      create: `curl -X POST "${baseUrl}/api/data/${tableName}" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{${dataString}}'`,
    };
  };

  const generateJsSnippets = () => {
    if (!model) return {};
    const tableName = model.table_name;

    return {
      list: `// List all ${model.display_name} records
const response = await fetch('/api/data/${tableName}', {
  headers: { 'Authorization': \`Bearer \${token}\` },
});
const { data } = await response.json();`,
      create: `// Create a new ${model.display_name}
const response = await fetch('/api/data/${tableName}', {
  method: 'POST',
  headers: {
    'Authorization': \`Bearer \${token}\`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ /* your data */ }),
});`,
    };
  };

  if (isLoading) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-96">
          <ArrowPathIcon className="w-8 h-8 text-[#3ecf8e] animate-spin" />
        </div>
      </Layout>
    );
  }

  if (error || !model) {
    return (
      <Layout>
        <div className="flex flex-col items-center justify-center h-96">
          <p className="text-red-400 mb-4">{error || 'Model not found'}</p>
          <Link to="/models" className="text-[#3ecf8e] hover:text-[#24b47e]">
            Back to Models
          </Link>
        </div>
      </Layout>
    );
  }

  const curlSnippets = generateCurlSnippets();
  const jsSnippets = generateJsSnippets();

  const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'api', label: 'API Endpoints' },
    { id: 'snippets', label: 'Code Snippets' },
    { id: 'data', label: 'Data Browser' },
  ];

  return (
    <Layout>
      <div className="p-6 lg:p-8 max-w-7xl mx-auto">
        {/* Page Header */}
        <div className="flex items-center gap-4 mb-6 animate-slideUp">
          <Link
            to="/models"
            className="p-2 text-[#a1a1a1] hover:text-white hover:bg-[#2a2a2a] rounded-md transition-all duration-200"
          >
            <ArrowLeftIcon className="w-5 h-5" />
          </Link>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#3ecf8e]/20 to-[#24b47e]/20 flex items-center justify-center">
              <TableCellsIcon className="w-5 h-5 text-[#3ecf8e]" />
            </div>
            <div>
              <h1 className="text-2xl font-semibold text-[#ededed]">{model.display_name}</h1>
              <p className="text-[#6b6b6b] text-sm">{model.table_name}</p>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg animate-slideUp" style={{ animationDelay: '100ms' }}>
          <div className="border-b border-[#3a3a3a]">
            <nav className="flex -mb-px">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as TabType)}
                  className={`px-6 py-4 text-sm font-medium border-b-2 transition-all duration-200 ${activeTab === tab.id
                    ? 'border-[#3ecf8e] text-[#3ecf8e]'
                    : 'border-transparent text-[#6b6b6b] hover:text-[#a1a1a1] hover:border-[#3a3a3a]'
                    }`}
                >
                  {tab.label}
                </button>
              ))}
            </nav>
          </div>

          <div className="p-6">
            {/* Overview Tab */}
            {activeTab === 'overview' && (
              <div className="space-y-6 animate-fadeIn">
                {model.description && <p className="text-[#a1a1a1]">{model.description}</p>}

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  {[
                    { label: 'Fields', value: model.fields.length },
                    { label: 'Timestamps', value: model.has_timestamps ? 'Yes' : 'No' },
                    { label: 'Soft Deletes', value: model.has_soft_deletes ? 'Yes' : 'No' },
                    { label: 'API Enabled', value: model.generate_api ? 'Yes' : 'No' },
                  ].map((stat) => (
                    <div key={stat.label} className="bg-[#323232] rounded-lg p-4">
                      <p className="text-sm text-[#6b6b6b]">{stat.label}</p>
                      <p className="text-2xl font-semibold text-[#ededed]">{stat.value}</p>
                    </div>
                  ))}
                </div>

                <div>
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-[#ededed]">Fields</h3>
                    <button
                      onClick={handleOpenAddFieldModal}
                      className="flex items-center gap-1.5 px-3 py-1.5 bg-[#3ecf8e]/10 text-[#3ecf8e] text-xs font-semibold rounded-md hover:bg-[#3ecf8e]/20 transition-all"
                    >
                      <PlusIcon className="w-4 h-4" />
                      Add Column
                    </button>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="min-w-full">
                      <thead>
                        <tr className="border-b border-[#3a3a3a]">
                          <th className="px-4 py-3 text-left text-xs font-medium text-[#6b6b6b] uppercase">Name</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-[#6b6b6b] uppercase">Type</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-[#6b6b6b] uppercase">Required</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-[#6b6b6b] uppercase">Unique</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[#3a3a3a]">
                        {model.fields.map((field) => (
                          <tr key={field.id}>
                            <td className="px-4 py-3">
                              <p className="font-medium text-[#ededed]">{field.display_name}</p>
                              <p className="text-sm text-[#6b6b6b]">{field.name}</p>
                            </td>
                            <td className="px-4 py-3">
                              <span className="px-2 py-1 text-xs font-medium bg-[#3ecf8e]/10 text-[#3ecf8e] rounded">
                                {field.type}
                              </span>
                            </td>
                            <td className="px-4 py-3">
                              <span className={field.is_required ? 'text-[#3ecf8e]' : 'text-[#6b6b6b]'}>
                                {field.is_required ? 'Yes' : 'No'}
                              </span>
                            </td>
                            <td className="px-4 py-3">
                              <span className={field.is_unique ? 'text-[#3ecf8e]' : 'text-[#6b6b6b]'}>
                                {field.is_unique ? 'Yes' : 'No'}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}

            {/* API Endpoints Tab */}
            {activeTab === 'api' && (
              <div className="space-y-4 animate-fadeIn">
                <p className="text-[#a1a1a1] mb-6">
                  Auto-generated REST API endpoints for your {model.display_name} model.
                </p>

                {[
                  { method: 'GET', path: `/api/data/${model.table_name}`, desc: 'List all records' },
                  { method: 'POST', path: `/api/data/${model.table_name}`, desc: 'Create a record' },
                  { method: 'GET', path: `/api/data/${model.table_name}/{id}`, desc: 'Get single record' },
                  { method: 'PUT', path: `/api/data/${model.table_name}/{id}`, desc: 'Update record' },
                  { method: 'DELETE', path: `/api/data/${model.table_name}/{id}`, desc: 'Delete record' },
                ].map((endpoint, i) => (
                  <div key={i} className="flex items-center gap-4 p-4 bg-[#323232] rounded-lg">
                    <span
                      className={`px-3 py-1 text-xs font-bold rounded ${endpoint.method === 'GET'
                        ? 'bg-[#3ecf8e]/10 text-[#3ecf8e]'
                        : endpoint.method === 'POST'
                          ? 'bg-blue-500/10 text-blue-400'
                          : endpoint.method === 'PUT'
                            ? 'bg-yellow-500/10 text-yellow-400'
                            : 'bg-red-500/10 text-red-400'
                        }`}
                    >
                      {endpoint.method}
                    </span>
                    <code className="flex-1 text-sm font-mono text-[#a1a1a1]">{endpoint.path}</code>
                    <span className="text-sm text-[#6b6b6b]">{endpoint.desc}</span>
                  </div>
                ))}
              </div>
            )}

            {/* Code Snippets Tab */}
            {activeTab === 'snippets' && (
              <div className="space-y-8 animate-fadeIn">
                {/* cURL */}
                <div>
                  <h3 className="text-lg font-semibold text-[#ededed] mb-4 flex items-center gap-2">
                    <CodeBracketIcon className="w-5 h-5 text-[#3ecf8e]" />
                    cURL
                  </h3>
                  <div className="space-y-4">
                    {Object.entries(curlSnippets).map(([key, snippet]) => (
                      <div key={key}>
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-sm font-medium text-[#a1a1a1] capitalize">{key}</span>
                          <button
                            onClick={() => copyToClipboard(snippet, `curl-${key}`)}
                            className="flex items-center gap-1 text-sm text-[#6b6b6b] hover:text-[#a1a1a1]"
                          >
                            {copiedSnippet === `curl-${key}` ? (
                              <>
                                <CheckIcon className="w-4 h-4 text-[#3ecf8e]" />
                                Copied!
                              </>
                            ) : (
                              <>
                                <DocumentDuplicateIcon className="w-4 h-4" />
                                Copy
                              </>
                            )}
                          </button>
                        </div>
                        <pre className="bg-[#171717] text-[#a1a1a1] p-4 rounded-lg overflow-x-auto text-sm border border-[#3a3a3a]">
                          {snippet}
                        </pre>
                      </div>
                    ))}
                  </div>
                </div>

                {/* JavaScript */}
                <div>
                  <h3 className="text-lg font-semibold text-[#ededed] mb-4 flex items-center gap-2">
                    <CodeBracketIcon className="w-5 h-5 text-[#3ecf8e]" />
                    JavaScript
                  </h3>
                  <div className="space-y-4">
                    {Object.entries(jsSnippets).map(([key, snippet]) => (
                      <div key={key}>
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-sm font-medium text-[#a1a1a1] capitalize">{key}</span>
                          <button
                            onClick={() => copyToClipboard(snippet, `js-${key}`)}
                            className="flex items-center gap-1 text-sm text-[#6b6b6b] hover:text-[#a1a1a1]"
                          >
                            {copiedSnippet === `js-${key}` ? (
                              <>
                                <CheckIcon className="w-4 h-4 text-[#3ecf8e]" />
                                Copied!
                              </>
                            ) : (
                              <>
                                <DocumentDuplicateIcon className="w-4 h-4" />
                                Copy
                              </>
                            )}
                          </button>
                        </div>
                        <pre className="bg-[#171717] text-[#a1a1a1] p-4 rounded-lg overflow-x-auto text-sm border border-[#3a3a3a]">
                          {snippet}
                        </pre>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}

            {/* Data Browser Tab */}
            {activeTab === 'data' && (
              <div className="animate-fadeIn">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-[#ededed]">Records ({dataMeta.total})</h3>
                  <div className="flex gap-2">
                    <button
                      onClick={() => handleOpenModal('create')}
                      className="flex items-center gap-2 px-4 py-2 text-sm bg-[#3ecf8e] text-black rounded-md hover:bg-[#24b47e] transition-all duration-200"
                    >
                      <PlusIcon className="w-4 h-4" />
                      Add Record
                    </button>
                    <button
                      onClick={fetchData}
                      className="flex items-center gap-2 px-4 py-2 text-sm text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a]"
                    >
                      <ArrowPathIcon className={`w-4 h-4 ${isLoadingData ? 'animate-spin' : ''}`} />
                      Refresh
                    </button>
                  </div>
                </div>

                {isLoadingData ? (
                  <div className="flex items-center justify-center py-12">
                    <ArrowPathIcon className="w-8 h-8 text-[#3ecf8e] animate-spin" />
                  </div>
                ) : records.length === 0 ? (
                  <div className="text-center py-12 bg-[#323232] rounded-lg">
                    <PlayIcon className="w-12 h-12 text-[#3a3a3a] mx-auto mb-4" />
                    <p className="text-[#a1a1a1] mb-2">No records yet</p>
                    <p className="text-sm text-[#6b6b6b]">
                      Use the API endpoints or the button above to create your first record
                    </p>
                  </div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="min-w-full">
                      <thead>
                        <tr className="border-b border-[#3a3a3a]">
                          <th className="px-4 py-3 text-left text-xs font-medium text-[#6b6b6b] uppercase">ID</th>
                          {model.fields.filter((f) => f.show_in_list).slice(0, 5).map((field) => (
                            <th key={field.id} className="px-4 py-3 text-left text-xs font-medium text-[#6b6b6b] uppercase">
                              {field.display_name}
                            </th>
                          ))}
                          <th className="px-4 py-3 text-right text-xs font-medium text-[#6b6b6b] uppercase">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[#3a3a3a]">
                        {records.map((record) => (
                          <tr key={record.id as number} className="group hover:bg-[#323232]">
                            <td className="px-4 py-3 text-sm text-[#ededed]">{record.id as number}</td>
                            {model.fields.filter((f) => f.show_in_list).slice(0, 5).map((field) => (
                              <td key={field.id} className="px-4 py-3 text-sm text-[#a1a1a1] max-w-xs truncate">
                                {String(record[field.name] ?? '-')}
                              </td>
                            ))}
                            <td className="px-4 py-3 text-right">
                              <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button
                                  onClick={() => handleOpenModal('edit', record)}
                                  className="p-1.5 text-[#a1a1a1] hover:text-[#3ecf8e] hover:bg-[#3ecf8e]/10 rounded transition-all"
                                >
                                  <PencilIcon className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => handleOpenModal('delete', record)}
                                  className="p-1.5 text-[#a1a1a1] hover:text-red-400 hover:bg-red-400/10 rounded transition-all"
                                >
                                  <TrashIcon className="w-4 h-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* CRUD Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 animate-fadeIn">
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg w-full max-w-lg mx-4 animate-slideUp overflow-hidden">
            <div className="flex items-center justify-between p-4 border-b border-[#3a3a3a]">
              <h3 className="text-lg font-semibold text-[#ededed]">
                {modalMode === 'create' ? 'Create Record' : modalMode === 'edit' ? 'Edit Record' : 'Delete Record'}
              </h3>
              <button onClick={handleCloseModal} className="text-[#6b6b6b] hover:text-white transition-colors">
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6">
              {modalMode === 'delete' ? (
                <div>
                  <p className="text-[#a1a1a1] mb-6">
                    Are you sure you want to delete this record (ID: {currentRecord?.id})? This action cannot be undone.
                  </p>
                  {formError && (
                    <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
                      {formError}
                    </div>
                  )}
                  <div className="flex gap-3">
                    <button
                      onClick={handleCloseModal}
                      className="flex-1 px-4 py-2 text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a] transition-all"
                    >
                      Cancel
                    </button>
                    <button
                      onClick={handleDeleteRecord}
                      disabled={isSubmitting}
                      className="flex-1 px-4 py-2 text-white bg-red-600 rounded-md hover:bg-red-700 transition-all disabled:opacity-50"
                    >
                      {isSubmitting ? 'Deleting...' : 'Delete'}
                    </button>
                  </div>
                </div>
              ) : (
                <form onSubmit={handleSubmitRecord} className="space-y-4">
                  {formError && (
                    <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
                      {formError}
                    </div>
                  )}

                  <div className="max-h-[60vh] overflow-y-auto space-y-4 pr-2 custom-scrollbar">
                    {model?.fields.map((field) => (
                      <div key={field.id}>
                        <label className="block text-sm font-medium text-[#a1a1a1] mb-1">
                          {field.display_name}
                          {field.is_required && <span className="text-red-400 ml-1">*</span>}
                        </label>

                        {field.type === 'boolean' ? (
                          <div className="flex items-center mt-2">
                            <input
                              type="checkbox"
                              name={field.name}
                              defaultChecked={modalMode === 'edit' ? !!currentRecord?.[field.name] : field.default_value === 'true'}
                              className="h-4 w-4 text-[#3ecf8e] bg-[#323232] border-[#3a3a3a] rounded focus:ring-[#3ecf8e]"
                            />
                            <span className="ml-2 text-sm text-[#6b6b6b]">Enable/True</span>
                          </div>
                        ) : field.type === 'text' || field.type === 'longText' ? (
                          <textarea
                            name={field.name}
                            required={field.is_required}
                            defaultValue={modalMode === 'edit' ? currentRecord?.[field.name] : field.default_value}
                            className="w-full px-3 py-2 bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-lg focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent placeholder-[#6b6b6b]"
                            rows={3}
                          />
                        ) : field.type === 'enum' || field.type === 'select' ? (
                          <select
                            name={field.name}
                            required={field.is_required}
                            defaultValue={modalMode === 'edit' ? currentRecord?.[field.name] : field.default_value}
                            className="w-full px-3 py-2 bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-lg focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent"
                          >
                            <option value="">Select option</option>
                            {field.options?.map(opt => (
                              <option key={opt} value={opt}>{opt}</option>
                            ))}
                          </select>
                        ) : (
                          <input
                            type={field.type === 'integer' || field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : 'text'}
                            name={field.name}
                            required={field.is_required}
                            defaultValue={modalMode === 'edit' ? currentRecord?.[field.name] : field.default_value}
                            className="w-full px-3 py-2 bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-lg focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent placeholder-[#6b6b6b]"
                          />
                        )}
                        {field.description && <p className="mt-1 text-xs text-[#6b6b6b]">{field.description}</p>}
                      </div>
                    ))}
                  </div>

                  <div className="flex gap-3 pt-4 border-t border-[#3a3a3a]">
                    <button
                      type="button"
                      onClick={handleCloseModal}
                      className="flex-1 px-4 py-2 text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a] transition-all"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="flex-1 px-4 py-2 text-black bg-[#3ecf8e] rounded-md hover:bg-[#24b47e] transition-all disabled:opacity-50"
                    >
                      {isSubmitting ? 'Saving...' : 'Save Record'}
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        </div>
      )}
      {/* Add Fields Modal */}
      {isAddFieldModalOpen && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4 animate-fadeIn">
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-slideUp">
            <div className="flex items-center justify-between p-6 border-b border-[#3a3a3a]">
              <div>
                <h3 className="text-xl font-semibold text-[#ededed]">Add New Columns</h3>
                <p className="text-sm text-[#6b6b6b]">Extend your model with more fields</p>
              </div>
              <button
                onClick={() => setIsAddFieldModalOpen(false)}
                className="p-2 text-[#6b6b6b] hover:text-[#ededed]"
              >
                <XMarkIcon className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-auto p-6">
              <div className="space-y-4">
                {newFields.map((field, idx) => (
                  <div key={idx} className="bg-[#323232] border border-[#3a3a3a] rounded-lg p-4 relative group animate-fadeIn">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                      <div>
                        <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Display Name</label>
                        <input
                          type="text"
                          value={field.display_name}
                          onChange={(e) => handleUpdateNewField(idx, { display_name: e.target.value })}
                          className="w-full bg-[#1c1c1c] border border-[#3a3a3a] text-[#ededed] rounded px-3 py-2 text-sm focus:ring-1 focus:ring-[#3ecf8e] outline-none"
                          placeholder="e.g. Price"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Field Name (ID)</label>
                        <input
                          type="text"
                          value={field.name}
                          onChange={(e) => handleUpdateNewField(idx, { name: e.target.value })}
                          className="w-full bg-[#1c1c1c] border border-[#3a3a3a] text-[#ededed] rounded px-3 py-2 text-sm focus:ring-1 focus:ring-[#3ecf8e] outline-none"
                          placeholder="e.g. price"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Type</label>
                        <select
                          value={field.type}
                          onChange={(e) => handleUpdateNewField(idx, { type: e.target.value })}
                          className="w-full bg-[#1c1c1c] border border-[#3a3a3a] text-[#ededed] rounded px-3 py-2 text-sm focus:ring-1 focus:ring-[#3ecf8e] outline-none"
                        >
                          {fieldTypes.map(type => (
                            <option key={type.value} value={type.value}>{type.label}</option>
                          ))}
                        </select>
                      </div>
                      <div className="flex items-end gap-3 pb-1">
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={field.is_required}
                            onChange={(e) => handleUpdateNewField(idx, { is_required: e.target.checked })}
                            className="w-4 h-4 rounded border-[#3a3a3a] bg-[#1c1c1c] text-[#3ecf8e] focus:ring-0"
                          />
                          <span className="text-xs text-[#ededed]">Required</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={field.is_unique}
                            onChange={(e) => handleUpdateNewField(idx, { is_unique: e.target.checked })}
                            className="w-4 h-4 rounded border-[#3a3a3a] bg-[#1c1c1c] text-[#3ecf8e] focus:ring-0"
                          />
                          <span className="text-xs text-[#ededed]">Unique</span>
                        </label>

                        {newFields.length > 1 && (
                          <button
                            onClick={() => handleRemoveNewFieldRow(idx)}
                            className="ml-auto p-1.5 text-[#6b6b6b] hover:text-red-400 hover:bg-red-400/10 rounded transition-all"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              <button
                onClick={handleAddNewFieldRow}
                className="w-full mt-4 py-3 border-2 border-dashed border-[#3a3a3a] rounded-lg text-[#6b6b6b] hover:text-[#3ecf8e] hover:border-[#3ecf8e]/50 hover:bg-[#3ecf8e]/5 transition-all text-sm font-medium flex items-center justify-center gap-2"
              >
                <PlusIcon className="w-5 h-5" />
                Add Another Column
              </button>
            </div>

            <div className="p-6 border-t border-[#3a3a3a] bg-[#1c1c1c]/50 flex gap-4">
              <button
                onClick={() => setIsAddFieldModalOpen(false)}
                className="flex-1 px-4 py-2 text-[#a1a1a1] bg-[#323232] rounded-md hover:bg-[#3a3a3a] transition-all"
              >
                Cancel
              </button>
              <button
                onClick={handleSubmitNewFields}
                disabled={isAddingFields || newFields.some(f => !f.name || !f.display_name)}
                className="flex-[2] px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-semibold rounded-md transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-[#3ecf8e]/10"
              >
                {isAddingFields ? (
                  <>
                    <ArrowPathIcon className="w-5 h-5 animate-spin" />
                    Updating Schema...
                  </>
                ) : (
                  'Save & Update Database'
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </Layout>
  );
}
