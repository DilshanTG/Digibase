import { useAuth } from '../hooks/useAuth';
import { Link } from 'react-router-dom';
import {
  CircleStackIcon,
  CodeBracketIcon,
  ServerIcon,
  FolderIcon,
  PlusIcon,
  TableCellsIcon,
  DocumentTextIcon,
  Cog6ToothIcon,
} from '@heroicons/react/24/outline';

export function Dashboard() {
  const { user, logout } = useAuth();

  const stats = [
    { name: 'Total Models', value: '0', icon: CircleStackIcon, color: 'from-blue-500 to-blue-600', href: '/models' },
    { name: 'API Endpoints', value: '0', icon: CodeBracketIcon, color: 'from-purple-500 to-purple-600', href: '#' },
    { name: 'Database Size', value: '0 MB', icon: ServerIcon, color: 'from-green-500 to-green-600', href: '#' },
    { name: 'Active Projects', value: '1', icon: FolderIcon, color: 'from-orange-500 to-orange-600', href: '#' },
  ];

  const quickActions = [
    { name: 'Create New Model', icon: PlusIcon, href: '/models/create', primary: true },
    { name: 'View All Models', icon: TableCellsIcon, href: '/models', primary: false },
    { name: 'View API Docs', icon: DocumentTextIcon, href: '/docs/api', primary: false, external: true },
    { name: 'Admin Panel', icon: Cog6ToothIcon, href: '/admin', primary: false, external: true },
  ];

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center py-4">
            <div className="flex items-center gap-6">
              <h1 className="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                Digibase
              </h1>
              <nav className="flex items-center gap-4">
                <Link to="/dashboard" className="text-blue-600 font-medium">
                  Dashboard
                </Link>
                <Link to="/models" className="text-gray-600 hover:text-gray-900">
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
        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {stats.map((stat) => (
            <div
              key={stat.name}
              className="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition"
            >
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-500">{stat.name}</p>
                  <p className="text-3xl font-bold text-gray-900 mt-1">{stat.value}</p>
                </div>
                <div className={`p-3 rounded-lg bg-gradient-to-r ${stat.color}`}>
                  <stat.icon className="h-6 w-6 text-white" />
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Quick Actions */}
        <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {quickActions.map((action) =>
              action.external ? (
                <a
                  key={action.name}
                  href={action.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={`flex flex-col items-center justify-center p-6 rounded-lg transition ${
                    action.primary
                      ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:from-blue-700 hover:to-purple-700'
                      : 'bg-gray-50 text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  <action.icon className="h-8 w-8 mb-2" />
                  <span className="text-sm font-medium text-center">{action.name}</span>
                </a>
              ) : (
                <Link
                  key={action.name}
                  to={action.href}
                  className={`flex flex-col items-center justify-center p-6 rounded-lg transition ${
                    action.primary
                      ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:from-blue-700 hover:to-purple-700'
                      : 'bg-gray-50 text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  <action.icon className="h-8 w-8 mb-2" />
                  <span className="text-sm font-medium text-center">{action.name}</span>
                </Link>
              )
            )}
          </div>
        </div>

        {/* Getting Started */}
        <div className="bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl p-6 border border-blue-100">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Getting Started</h2>
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-medium">
                1
              </div>
              <span className="text-gray-700">Create your first model using the Visual Model Creator</span>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium">
                2
              </div>
              <span className="text-gray-500">Test the auto-generated API endpoints</span>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium">
                3
              </div>
              <span className="text-gray-500">Build your frontend with generated code snippets</span>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium">
                4
              </div>
              <span className="text-gray-500">Deploy your application</span>
            </div>
          </div>
        </div>

        {/* System Status */}
        <div className="mt-8 bg-white rounded-xl shadow-sm p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">System Status</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-green-500"></div>
              <span className="text-sm text-gray-600">Database Connected</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-green-500"></div>
              <span className="text-sm text-gray-600">API Active</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-gray-300"></div>
              <span className="text-sm text-gray-600">Queue Worker</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-gray-300"></div>
              <span className="text-sm text-gray-600">Real-time</span>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
