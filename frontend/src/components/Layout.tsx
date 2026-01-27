import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import {
  HomeIcon,
  CubeIcon,
  CircleStackIcon,
  FolderIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  Bars3Icon,
  XMarkIcon,
  CommandLineIcon,
  CodeBracketIcon,
  UsersIcon,
  KeyIcon,
  ArrowPathIcon,
  ShieldCheckIcon
} from '@heroicons/react/24/outline';
import { useState, useEffect, type ReactNode } from 'react';
import api from '../lib/api';

interface LayoutProps {
  children: ReactNode;
}

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
  { name: 'Models', href: '/models', icon: CubeIcon },
  { name: 'Database', href: '/database', icon: CircleStackIcon },
  { name: 'Storage', href: '/storage', icon: FolderIcon },
  { name: 'Users', href: '/users', icon: UsersIcon },
  { name: 'Roles', href: '/roles', icon: ShieldCheckIcon },
  { name: 'API Keys', href: '/api-keys', icon: KeyIcon },
  { name: 'Code Generator', href: '/code-generator', icon: CodeBracketIcon },
  { name: 'API Docs', href: '/api-docs', icon: CommandLineIcon },
  { name: 'Settings', href: '/settings', icon: Cog6ToothIcon },
  { name: 'Migrations', href: '/migrations', icon: ArrowPathIcon },
];

export function Layout({ children }: LayoutProps) {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [projectName, setProjectName] = useState('Digibase');

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        const response = await api.get('/settings');
        // settings are grouped by group, project_name is in 'general'
        const generalSettings = response.data.general || [];
        const nameSetting = generalSettings.find((s: any) => s.key === 'project_name');
        if (nameSetting) {
          setProjectName(nameSetting.value);
        }
      } catch (error) {
        console.error('Failed to fetch project name:', error);
      }
    };
    fetchSettings();
  }, []);

  const isActive = (href: string) => {
    if (href === '/dashboard') return location.pathname === '/dashboard';
    return location.pathname.startsWith(href);
  };

  return (
    <div className="min-h-screen flex" style={{ backgroundColor: 'var(--bg-primary)' }}>
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden animate-fadeIn"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed lg:static inset-y-0 left-0 z-50 w-64 flex flex-col transform transition-transform duration-300 ease-out ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
          }`}
        style={{ backgroundColor: 'var(--bg-surface)', borderRight: '1px solid var(--border-color)' }}
      >
        {/* Logo */}
        <div className="h-14 flex items-center justify-between px-4" style={{ borderBottom: '1px solid var(--border-color)' }}>
          <Link to="/dashboard" className="flex items-center gap-2">
            <div className="w-8 h-8 bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] rounded-lg flex items-center justify-center shadow-lg shadow-[#3ecf8e]/20">
              <span className="text-white font-bold text-sm">
                {projectName.charAt(0).toUpperCase()}
              </span>
            </div>
            <span className="font-semibold text-lg tracking-tight" style={{ color: 'var(--text-primary)' }}>
              {projectName}
            </span>
          </Link>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden p-1 hover:text-white" style={{ color: 'var(--text-secondary)' }}
          >
            <XMarkIcon className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {navigation.map((item, index) => {
            const active = isActive(item.href);
            return (
              <Link
                key={item.name}
                to={item.href}
                onClick={() => setSidebarOpen(false)}
                className={`flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 animate-slideIn ${active
                  ? 'bg-[#2a2a2a] text-white'
                  : 'text-[#a1a1a1] hover:bg-[#2a2a2a]/50 hover:text-white'
                  }`}
                style={{ animationDelay: `${index * 50}ms` }}
              >
                <item.icon className={`w-5 h-5 ${active ? 'text-[#3ecf8e]' : ''}`} />
                {item.name}
                {active && (
                  <div className="ml-auto w-1.5 h-1.5 rounded-full bg-[#3ecf8e]" />
                )}
              </Link>
            );
          })}
        </nav>

        {/* User section */}
        <div className="p-3" style={{ borderTop: '1px solid var(--border-color)' }}>
          <div className="flex items-center gap-3 px-3 py-2 rounded-md" style={{ backgroundColor: 'var(--bg-secondary)' }}>
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] flex items-center justify-center">
              <span className="text-white text-sm font-medium">
                {user?.name?.charAt(0).toUpperCase()}
              </span>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium truncate" style={{ color: 'var(--text-primary)' }}>{user?.name}</p>
              <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>{user?.email}</p>
            </div>
          </div>
          <button
            onClick={logout}
            className="w-full mt-2 flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-all duration-200"
            style={{ color: 'var(--text-secondary)' }}
          >
            <ArrowRightOnRectangleIcon className="w-5 h-5" />
            Sign out
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top bar */}
        <header className="h-14 flex items-center px-4 lg:px-6" style={{ backgroundColor: 'var(--bg-surface)', borderBottom: '1px solid var(--border-color)' }}>
          <button
            onClick={() => setSidebarOpen(true)}
            className="lg:hidden p-2 -ml-2" style={{ color: 'var(--text-secondary)' }}
          >
            <Bars3Icon className="w-5 h-5" />
          </button>

          <div className="flex-1" />

          <div className="flex items-center gap-3">
            <Link
              to="/settings"
              className="p-2 rounded-md transition-all duration-200"
              style={{ color: 'var(--text-secondary)' }}
            >
              <Cog6ToothIcon className="w-5 h-5" />
            </Link>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-auto">
          <div className="animate-fadeIn">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
