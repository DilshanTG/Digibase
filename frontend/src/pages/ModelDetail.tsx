import { useState, useEffect } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import api from '../lib/api';
import {
  ArrowLeftIcon,
  TableCellsIcon,
  CodeBracketIcon,
  DocumentDuplicateIcon,
  CheckIcon,
  PlayIcon,
  ArrowPathIcon,
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

export function ModelDetail() {
  const { user, logout } = useAuth();
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

  useEffect(() => {
    const fetchModel = async () => {
      try {
        setIsLoading(true);
        const response = await api.get(`/models/${id}`);
        setModel(response.data);
        setError('');
      } catch (err: unknown) {
        const errorMessage = err instanceof Error ? err.message : 'Failed to load model';
        setError(errorMessage);
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
      setRecords(response.data.data);
      setDataMeta(response.data.meta);
    } catch {
      // Table might not have data yet
      setRecords([]);
    } finally {
      setIsLoadingData(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'data' && model) {
      fetchData();
    }
  }, [activeTab, model]);

  const copyToClipboard = async (text: string, snippetId: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedSnippet(snippetId);
    setTimeout(() => setCopiedSnippet(null), 2000);
  };

  const getBaseUrl = () => {
    return window.location.origin.replace(':5173', ':8000');
  };

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
  -H "Accept: application/json" \\
  -d '{${dataString}}'`,
      show: `curl -X GET "${baseUrl}/api/data/${tableName}/1" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Accept: application/json"`,
      update: `curl -X PUT "${baseUrl}/api/data/${tableName}/1" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{${dataString}}'`,
      delete: `curl -X DELETE "${baseUrl}/api/data/${tableName}/1" \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Accept: application/json"`,
    };
  };

  const generateJavaScriptSnippets = () => {
    if (!model) return {};
    const tableName = model.table_name;

    const sampleData = model.fields.reduce((acc, field) => {
      switch (field.type) {
        case 'string':
        case 'text':
          acc[field.name] = `Sample ${field.display_name}`;
          break;
        case 'email':
          acc[field.name] = 'user@example.com';
          break;
        case 'integer':
          acc[field.name] = 1;
          break;
        case 'boolean':
          acc[field.name] = true;
          break;
        default:
          acc[field.name] = 'value';
      }
      return acc;
    }, {} as Record<string, unknown>);

    return {
      list: `// List all ${model.display_name} records
const response = await fetch('/api/data/${tableName}', {
  headers: {
    'Authorization': \`Bearer \${token}\`,
    'Accept': 'application/json',
  },
});
const { data, meta } = await response.json();
console.log(data); // Array of records
console.log(meta); // Pagination info`,
      create: `// Create a new ${model.display_name}
const response = await fetch('/api/data/${tableName}', {
  method: 'POST',
  headers: {
    'Authorization': \`Bearer \${token}\`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify(${JSON.stringify(sampleData, null, 2)}),
});
const { data } = await response.json();
console.log(data); // Created record`,
      show: `// Get a single ${model.display_name}
const response = await fetch('/api/data/${tableName}/1', {
  headers: {
    'Authorization': \`Bearer \${token}\`,
    'Accept': 'application/json',
  },
});
const { data } = await response.json();
console.log(data); // Single record`,
      update: `// Update a ${model.display_name}
const response = await fetch('/api/data/${tableName}/1', {
  method: 'PUT',
  headers: {
    'Authorization': \`Bearer \${token}\`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify(${JSON.stringify(sampleData, null, 2)}),
});
const { data } = await response.json();
console.log(data); // Updated record`,
      delete: `// Delete a ${model.display_name}
const response = await fetch('/api/data/${tableName}/1', {
  method: 'DELETE',
  headers: {
    'Authorization': \`Bearer \${token}\`,
    'Accept': 'application/json',
  },
});
const result = await response.json();
console.log(result.message); // "Record deleted successfully"`,
    };
  };

  const generateReactQuerySnippets = () => {
    if (!model) return {};
    const tableName = model.table_name;
    const modelName = model.name;

    return {
      hooks: `// hooks/use${modelName}.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

interface ${modelName} {
  id: number;
${model.fields.map(f => `  ${f.name}: ${f.type === 'integer' ? 'number' : f.type === 'boolean' ? 'boolean' : 'string'};`).join('\n')}
}

export function use${modelName}s() {
  return useQuery({
    queryKey: ['${tableName}'],
    queryFn: async () => {
      const response = await api.get('/data/${tableName}');
      return response.data;
    },
  });
}

export function use${modelName}(id: number) {
  return useQuery({
    queryKey: ['${tableName}', id],
    queryFn: async () => {
      const response = await api.get(\`/data/${tableName}/\${id}\`);
      return response.data;
    },
  });
}

export function useCreate${modelName}() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Omit<${modelName}, 'id'>) =>
      api.post('/data/${tableName}', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['${tableName}'] });
    },
  });
}

export function useUpdate${modelName}() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<${modelName}> }) =>
      api.put(\`/data/${tableName}/\${id}\`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['${tableName}'] });
    },
  });
}

export function useDelete${modelName}() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(\`/data/${tableName}/\${id}\`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['${tableName}'] });
    },
  });
}`,
    };
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <ArrowPathIcon className="h-8 w-8 text-blue-600 animate-spin" />
      </div>
    );
  }

  if (error || !model) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-600 mb-4">{error || 'Model not found'}</p>
          <Link to="/models" className="text-blue-600 hover:underline">
            Back to Models
          </Link>
        </div>
      </div>
    );
  }

  const curlSnippets = generateCurlSnippets();
  const jsSnippets = generateJavaScriptSnippets();
  const reactQuerySnippets = generateReactQuerySnippets();

  const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'api', label: 'API Endpoints' },
    { id: 'snippets', label: 'Code Snippets' },
    { id: 'data', label: 'Data Browser' },
  ];

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
                <span className="text-blue-600 font-medium">{model.display_name}</span>
              </nav>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-gray-600">Welcome, {user?.name}</span>
              <button onClick={logout} className="text-gray-600 hover:text-gray-900 font-medium">
                Logout
              </button>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Page Header */}
        <div className="flex items-center gap-4 mb-6">
          <Link
            to="/models"
            className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-200 rounded-lg transition"
          >
            <ArrowLeftIcon className="h-5 w-5" />
          </Link>
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-100 rounded-lg">
              <TableCellsIcon className="h-6 w-6 text-blue-600" />
            </div>
            <div>
              <h2 className="text-2xl font-bold text-gray-900">{model.display_name}</h2>
              <p className="text-gray-500 text-sm">{model.table_name}</p>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="bg-white rounded-xl shadow-sm mb-6">
          <div className="border-b">
            <nav className="flex -mb-px">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as TabType)}
                  className={`px-6 py-4 text-sm font-medium border-b-2 transition ${
                    activeTab === tab.id
                      ? 'border-blue-600 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
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
              <div className="space-y-6">
                {model.description && (
                  <p className="text-gray-600">{model.description}</p>
                )}

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="bg-gray-50 rounded-lg p-4">
                    <p className="text-sm text-gray-500">Fields</p>
                    <p className="text-2xl font-bold text-gray-900">{model.fields.length}</p>
                  </div>
                  <div className="bg-gray-50 rounded-lg p-4">
                    <p className="text-sm text-gray-500">Timestamps</p>
                    <p className="text-2xl font-bold text-gray-900">{model.has_timestamps ? 'Yes' : 'No'}</p>
                  </div>
                  <div className="bg-gray-50 rounded-lg p-4">
                    <p className="text-sm text-gray-500">Soft Deletes</p>
                    <p className="text-2xl font-bold text-gray-900">{model.has_soft_deletes ? 'Yes' : 'No'}</p>
                  </div>
                  <div className="bg-gray-50 rounded-lg p-4">
                    <p className="text-sm text-gray-500">API Enabled</p>
                    <p className="text-2xl font-bold text-gray-900">{model.generate_api ? 'Yes' : 'No'}</p>
                  </div>
                </div>

                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-4">Fields</h3>
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Required</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unique</th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {model.fields.map((field) => (
                          <tr key={field.id}>
                            <td className="px-4 py-3">
                              <div>
                                <p className="font-medium text-gray-900">{field.display_name}</p>
                                <p className="text-sm text-gray-500">{field.name}</p>
                              </div>
                            </td>
                            <td className="px-4 py-3">
                              <span className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                {field.type}
                              </span>
                            </td>
                            <td className="px-4 py-3">
                              {field.is_required ? (
                                <span className="text-green-600">Yes</span>
                              ) : (
                                <span className="text-gray-400">No</span>
                              )}
                            </td>
                            <td className="px-4 py-3">
                              {field.is_unique ? (
                                <span className="text-green-600">Yes</span>
                              ) : (
                                <span className="text-gray-400">No</span>
                              )}
                            </td>
                            <td className="px-4 py-3 text-gray-500">
                              {field.default_value || '-'}
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
              <div className="space-y-4">
                <p className="text-gray-600 mb-6">
                  Auto-generated REST API endpoints for your {model.display_name} model.
                </p>

                {[
                  { method: 'GET', path: `/api/data/${model.table_name}`, desc: 'List all records with pagination' },
                  { method: 'POST', path: `/api/data/${model.table_name}`, desc: 'Create a new record' },
                  { method: 'GET', path: `/api/data/${model.table_name}/{id}`, desc: 'Get a single record' },
                  { method: 'PUT', path: `/api/data/${model.table_name}/{id}`, desc: 'Update a record' },
                  { method: 'DELETE', path: `/api/data/${model.table_name}/{id}`, desc: 'Delete a record' },
                  { method: 'GET', path: `/api/data/${model.table_name}/schema`, desc: 'Get model schema' },
                ].map((endpoint, i) => (
                  <div key={i} className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                    <span
                      className={`px-3 py-1 text-xs font-bold rounded ${
                        endpoint.method === 'GET'
                          ? 'bg-green-100 text-green-800'
                          : endpoint.method === 'POST'
                          ? 'bg-blue-100 text-blue-800'
                          : endpoint.method === 'PUT'
                          ? 'bg-yellow-100 text-yellow-800'
                          : 'bg-red-100 text-red-800'
                      }`}
                    >
                      {endpoint.method}
                    </span>
                    <code className="flex-1 text-sm font-mono text-gray-700">{endpoint.path}</code>
                    <span className="text-sm text-gray-500">{endpoint.desc}</span>
                  </div>
                ))}

                <div className="mt-6 p-4 bg-blue-50 rounded-lg">
                  <h4 className="font-medium text-blue-900 mb-2">Query Parameters (GET list)</h4>
                  <ul className="text-sm text-blue-800 space-y-1">
                    <li><code>?search=term</code> - Search across searchable fields</li>
                    <li><code>?sort=field&direction=asc|desc</code> - Sort results</li>
                    <li><code>?per_page=15</code> - Items per page (max 100)</li>
                    <li><code>?page=1</code> - Page number</li>
                    {model.fields.filter(f => f.is_filterable).map(f => (
                      <li key={f.name}><code>?{f.name}=value</code> - Filter by {f.display_name}</li>
                    ))}
                  </ul>
                </div>
              </div>
            )}

            {/* Code Snippets Tab */}
            {activeTab === 'snippets' && (
              <div className="space-y-8">
                {/* cURL */}
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <CodeBracketIcon className="h-5 w-5" />
                    cURL
                  </h3>
                  <div className="space-y-4">
                    {Object.entries(curlSnippets).map(([key, snippet]) => (
                      <div key={key} className="relative">
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-sm font-medium text-gray-700 capitalize">{key}</span>
                          <button
                            onClick={() => copyToClipboard(snippet, `curl-${key}`)}
                            className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                          >
                            {copiedSnippet === `curl-${key}` ? (
                              <>
                                <CheckIcon className="h-4 w-4 text-green-600" />
                                Copied!
                              </>
                            ) : (
                              <>
                                <DocumentDuplicateIcon className="h-4 w-4" />
                                Copy
                              </>
                            )}
                          </button>
                        </div>
                        <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">
                          {snippet}
                        </pre>
                      </div>
                    ))}
                  </div>
                </div>

                {/* JavaScript */}
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <CodeBracketIcon className="h-5 w-5" />
                    JavaScript (Fetch)
                  </h3>
                  <div className="space-y-4">
                    {Object.entries(jsSnippets).map(([key, snippet]) => (
                      <div key={key} className="relative">
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-sm font-medium text-gray-700 capitalize">{key}</span>
                          <button
                            onClick={() => copyToClipboard(snippet, `js-${key}`)}
                            className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                          >
                            {copiedSnippet === `js-${key}` ? (
                              <>
                                <CheckIcon className="h-4 w-4 text-green-600" />
                                Copied!
                              </>
                            ) : (
                              <>
                                <DocumentDuplicateIcon className="h-4 w-4" />
                                Copy
                              </>
                            )}
                          </button>
                        </div>
                        <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">
                          {snippet}
                        </pre>
                      </div>
                    ))}
                  </div>
                </div>

                {/* React Query */}
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <CodeBracketIcon className="h-5 w-5" />
                    React Query Hooks
                  </h3>
                  <div className="relative">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium text-gray-700">Complete Hooks File</span>
                      <button
                        onClick={() => copyToClipboard(reactQuerySnippets.hooks || '', 'react-hooks')}
                        className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                      >
                        {copiedSnippet === 'react-hooks' ? (
                          <>
                            <CheckIcon className="h-4 w-4 text-green-600" />
                            Copied!
                          </>
                        ) : (
                          <>
                            <DocumentDuplicateIcon className="h-4 w-4" />
                            Copy
                          </>
                        )}
                      </button>
                    </div>
                    <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm max-h-96">
                      {reactQuerySnippets.hooks}
                    </pre>
                  </div>
                </div>
              </div>
            )}

            {/* Data Browser Tab */}
            {activeTab === 'data' && (
              <div>
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">
                    Records ({dataMeta.total})
                  </h3>
                  <button
                    onClick={fetchData}
                    className="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                  >
                    <ArrowPathIcon className={`h-4 w-4 ${isLoadingData ? 'animate-spin' : ''}`} />
                    Refresh
                  </button>
                </div>

                {isLoadingData ? (
                  <div className="flex items-center justify-center py-12">
                    <ArrowPathIcon className="h-8 w-8 text-blue-600 animate-spin" />
                  </div>
                ) : records.length === 0 ? (
                  <div className="text-center py-12 bg-gray-50 rounded-lg">
                    <PlayIcon className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                    <p className="text-gray-600 mb-2">No records yet</p>
                    <p className="text-sm text-gray-500">
                      Use the API endpoints or code snippets to create your first record
                    </p>
                  </div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                          {model.fields.filter(f => f.show_in_list).slice(0, 5).map((field) => (
                            <th key={field.id} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                              {field.display_name}
                            </th>
                          ))}
                          {model.has_timestamps && (
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                          )}
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {records.map((record) => (
                          <tr key={record.id as number}>
                            <td className="px-4 py-3 text-sm text-gray-900">{record.id as number}</td>
                            {model.fields.filter(f => f.show_in_list).slice(0, 5).map((field) => (
                              <td key={field.id} className="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                                {String(record[field.name] ?? '-')}
                              </td>
                            ))}
                            {model.has_timestamps && (
                              <td className="px-4 py-3 text-sm text-gray-500">
                                {record.created_at ? new Date(record.created_at as string).toLocaleDateString() : '-'}
                              </td>
                            )}
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
      </main>
    </div>
  );
}
