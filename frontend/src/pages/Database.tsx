import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import api from '../lib/api';
import {
  CircleStackIcon,
  TableCellsIcon,
  PlayIcon,
  ArrowPathIcon,
  ChevronRightIcon,
  MagnifyingGlassIcon,
} from '@heroicons/react/24/outline';

interface TableInfo {
  name: string;
  rows: number;
  is_dynamic: boolean;
  dynamic_model_id: number | null;
  dynamic_model_name: string | null;
}

interface ColumnInfo {
  name: string;
  type: string;
  nullable: boolean;
  default: string | null;
}

interface DbStats {
  total_tables: number;
  total_rows: number;
  dynamic_models: number;
}

type ViewMode = 'tables' | 'structure' | 'data' | 'query';

export function Database() {
  const { user, logout } = useAuth();
  const [viewMode, setViewMode] = useState<ViewMode>('tables');
  const [tables, setTables] = useState<TableInfo[]>([]);
  const [stats, setStats] = useState<DbStats | null>(null);
  const [selectedTable, setSelectedTable] = useState<string | null>(null);
  const [columns, setColumns] = useState<ColumnInfo[]>([]);
  const [tableData, setTableData] = useState<Record<string, unknown>[]>([]);
  const [dataColumns, setDataColumns] = useState<string[]>([]);
  const [dataMeta, setDataMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');

  // SQL Query state
  const [sqlQuery, setSqlQuery] = useState('SELECT * FROM users LIMIT 10');
  const [queryResults, setQueryResults] = useState<Record<string, unknown>[]>([]);
  const [queryColumns, setQueryColumns] = useState<string[]>([]);
  const [queryError, setQueryError] = useState('');
  const [queryTime, setQueryTime] = useState<number | null>(null);
  const [isQuerying, setIsQuerying] = useState(false);

  const fetchTables = async () => {
    try {
      setIsLoading(true);
      const [tablesRes, statsRes] = await Promise.all([
        api.get('/database/tables'),
        api.get('/database/stats'),
      ]);
      setTables(tablesRes.data.data);
      setStats(statsRes.data);
    } catch {
      setError('Failed to load tables');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchTables();
  }, []);

  const fetchTableStructure = async (tableName: string) => {
    try {
      setIsLoading(true);
      const response = await api.get(`/database/tables/${tableName}/structure`);
      setColumns(response.data.columns);
      setSelectedTable(tableName);
      setViewMode('structure');
    } catch {
      setError('Failed to load table structure');
    } finally {
      setIsLoading(false);
    }
  };

  const fetchTableData = async (tableName: string, page = 1) => {
    try {
      setIsLoading(true);
      const params = new URLSearchParams({ page: String(page) });
      if (search) params.append('search', search);

      const response = await api.get(`/database/tables/${tableName}/data?${params}`);
      setTableData(response.data.data);
      setDataColumns(response.data.columns);
      setDataMeta(response.data.meta);
      setSelectedTable(tableName);
      setViewMode('data');
    } catch {
      setError('Failed to load table data');
    } finally {
      setIsLoading(false);
    }
  };

  const executeQuery = async () => {
    if (!sqlQuery.trim()) return;

    setIsQuerying(true);
    setQueryError('');
    setQueryResults([]);
    setQueryColumns([]);
    setQueryTime(null);

    try {
      const response = await api.post('/database/query', { sql: sqlQuery });
      setQueryResults(response.data.data);
      setQueryColumns(response.data.columns);
      setQueryTime(response.data.execution_time_ms);
    } catch (err: unknown) {
      interface ApiError {
        response?: {
          data?: {
            message?: string;
          };
        };
      }
      const apiError = err as ApiError;
      setQueryError(apiError.response?.data?.message || 'Query failed');
    } finally {
      setIsQuerying(false);
    }
  };

  const filteredTables = tables.filter((t) =>
    t.name.toLowerCase().includes(search.toLowerCase())
  );

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
                <Link to="/storage" className="text-gray-600 hover:text-gray-900">Storage</Link>
                <span className="text-blue-600 font-medium">Database</span>
              </nav>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-gray-600">Welcome, {user?.name}</span>
              <button onClick={logout} className="text-gray-600 hover:text-gray-900 font-medium">Logout</button>
            </div>
          </div>
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="flex gap-6">
          {/* Sidebar */}
          <div className="w-64 flex-shrink-0">
            {/* Stats */}
            {stats && (
              <div className="bg-white rounded-xl shadow-sm p-4 mb-4">
                <h3 className="font-semibold text-gray-900 mb-3">Database Stats</h3>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-500">Tables</span>
                    <span className="font-medium">{stats.total_tables}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Total Rows</span>
                    <span className="font-medium">{stats.total_rows.toLocaleString()}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Dynamic Models</span>
                    <span className="font-medium">{stats.dynamic_models}</span>
                  </div>
                </div>
              </div>
            )}

            {/* Tables List */}
            <div className="bg-white rounded-xl shadow-sm">
              <div className="p-4 border-b">
                <div className="relative">
                  <MagnifyingGlassIcon className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Search tables..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
              <div className="max-h-96 overflow-y-auto">
                {filteredTables.map((table) => (
                  <button
                    key={table.name}
                    onClick={() => fetchTableData(table.name)}
                    className={`w-full flex items-center justify-between px-4 py-2 text-left hover:bg-gray-50 ${
                      selectedTable === table.name ? 'bg-blue-50 text-blue-600' : ''
                    }`}
                  >
                    <div className="flex items-center gap-2">
                      <TableCellsIcon className="h-4 w-4 text-gray-400" />
                      <span className="text-sm truncate">{table.name}</span>
                      {table.is_dynamic && (
                        <span className="px-1.5 py-0.5 text-xs bg-purple-100 text-purple-700 rounded">
                          dynamic
                        </span>
                      )}
                    </div>
                    <span className="text-xs text-gray-400">{table.rows}</span>
                  </button>
                ))}
              </div>
            </div>

            {/* SQL Query Button */}
            <button
              onClick={() => setViewMode('query')}
              className={`w-full mt-4 flex items-center gap-2 px-4 py-3 rounded-xl ${
                viewMode === 'query'
                  ? 'bg-blue-600 text-white'
                  : 'bg-white text-gray-700 hover:bg-gray-50'
              } shadow-sm`}
            >
              <PlayIcon className="h-5 w-5" />
              SQL Query Editor
            </button>
          </div>

          {/* Main Content */}
          <div className="flex-1">
            {error && (
              <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                {error}
              </div>
            )}

            {/* Tables View */}
            {viewMode === 'tables' && (
              <div className="bg-white rounded-xl shadow-sm p-6">
                <div className="flex items-center gap-3 mb-6">
                  <CircleStackIcon className="h-6 w-6 text-blue-600" />
                  <h2 className="text-xl font-semibold text-gray-900">Database Tables</h2>
                </div>
                <p className="text-gray-600">Select a table from the sidebar to view its data.</p>
              </div>
            )}

            {/* Structure View */}
            {viewMode === 'structure' && selectedTable && (
              <div className="bg-white rounded-xl shadow-sm">
                <div className="p-4 border-b flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <TableCellsIcon className="h-5 w-5 text-blue-600" />
                    <h2 className="font-semibold text-gray-900">{selectedTable}</h2>
                    <ChevronRightIcon className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-600">Structure</span>
                  </div>
                  <button
                    onClick={() => fetchTableData(selectedTable)}
                    className="text-sm text-blue-600 hover:underline"
                  >
                    View Data
                  </button>
                </div>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Column</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nullable</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {columns.map((col) => (
                        <tr key={col.name}>
                          <td className="px-4 py-3 text-sm font-medium text-gray-900">{col.name}</td>
                          <td className="px-4 py-3 text-sm text-gray-600">{col.type}</td>
                          <td className="px-4 py-3 text-sm">{col.nullable ? 'Yes' : 'No'}</td>
                          <td className="px-4 py-3 text-sm text-gray-500">{col.default || '-'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Data View */}
            {viewMode === 'data' && selectedTable && (
              <div className="bg-white rounded-xl shadow-sm">
                <div className="p-4 border-b flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <TableCellsIcon className="h-5 w-5 text-blue-600" />
                    <h2 className="font-semibold text-gray-900">{selectedTable}</h2>
                    <ChevronRightIcon className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-600">Data ({dataMeta.total} rows)</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => fetchTableStructure(selectedTable)}
                      className="text-sm text-blue-600 hover:underline"
                    >
                      View Structure
                    </button>
                    <button
                      onClick={() => fetchTableData(selectedTable)}
                      className="p-2 text-gray-500 hover:text-gray-700"
                    >
                      <ArrowPathIcon className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                    </button>
                  </div>
                </div>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        {dataColumns.map((col) => (
                          <th key={col} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                            {col}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {tableData.map((row, i) => (
                        <tr key={i}>
                          {dataColumns.map((col) => (
                            <td key={col} className="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                              {String(row[col] ?? '-')}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {/* Pagination */}
                {dataMeta.last_page > 1 && (
                  <div className="p-4 border-t flex items-center justify-between">
                    <span className="text-sm text-gray-500">
                      Page {dataMeta.current_page} of {dataMeta.last_page}
                    </span>
                    <div className="flex gap-2">
                      <button
                        onClick={() => fetchTableData(selectedTable, dataMeta.current_page - 1)}
                        disabled={dataMeta.current_page <= 1}
                        className="px-3 py-1 text-sm border rounded disabled:opacity-50"
                      >
                        Previous
                      </button>
                      <button
                        onClick={() => fetchTableData(selectedTable, dataMeta.current_page + 1)}
                        disabled={dataMeta.current_page >= dataMeta.last_page}
                        className="px-3 py-1 text-sm border rounded disabled:opacity-50"
                      >
                        Next
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* SQL Query View */}
            {viewMode === 'query' && (
              <div className="space-y-4">
                <div className="bg-white rounded-xl shadow-sm p-4">
                  <h2 className="font-semibold text-gray-900 mb-3">SQL Query Editor</h2>
                  <p className="text-sm text-gray-500 mb-4">
                    Execute read-only SELECT queries. INSERT, UPDATE, DELETE are blocked for safety.
                  </p>
                  <textarea
                    value={sqlQuery}
                    onChange={(e) => setSqlQuery(e.target.value)}
                    className="w-full h-32 px-4 py-3 font-mono text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="SELECT * FROM users LIMIT 10"
                  />
                  <div className="flex items-center justify-between mt-3">
                    <button
                      onClick={executeQuery}
                      disabled={isQuerying}
                      className="flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50"
                    >
                      <PlayIcon className="h-4 w-4" />
                      {isQuerying ? 'Running...' : 'Run Query'}
                    </button>
                    {queryTime !== null && (
                      <span className="text-sm text-gray-500">
                        Executed in {queryTime}ms ({queryResults.length} rows)
                      </span>
                    )}
                  </div>
                </div>

                {queryError && (
                  <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    {queryError}
                  </div>
                )}

                {queryResults.length > 0 && (
                  <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                          <tr>
                            {queryColumns.map((col) => (
                              <th key={col} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                {col}
                              </th>
                            ))}
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                          {queryResults.map((row, i) => (
                            <tr key={i}>
                              {queryColumns.map((col) => (
                                <td key={col} className="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                                  {String(row[col] ?? '-')}
                                </td>
                              ))}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
