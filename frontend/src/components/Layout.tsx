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
} from '@heroicons/react/24/outline';
import { useState, type ReactNode } from 'react';

interface LayoutProps {
  children: ReactNode;
}

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
  { name: 'Models', href: '/models', icon: CubeIcon },
  { name: 'Database', href: '/database', icon: CircleStackIcon },
  { name: 'Storage', href: '/storage', icon: FolderIcon },
];

export function Layout({ children }: LayoutProps) {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const isActive = (href: string) => {
    if (href === '/dashboard') return location.pathname === '/dashboard';
    return location.pathname.startsWith(href);
  };

  return (
    <div className="min-h-screen bg-[#1c1c1c] flex">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden animate-fadeIn"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed lg:static inset-y-0 left-0 z-50 w-64 bg-[#171717] border-r border-[#2a2a2a] flex flex-col transform transition-transform duration-300 ease-out ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        }`}
      >
        {/* Logo */}
        <div className="h-14 flex items-center justify-between px-4 border-b border-[#2a2a2a]">
          <Link to="/dashboard" className="flex items-center gap-2">
            <div className="w-8 h-8 bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-sm">D</span>
            </div>
            <span className="text-[#ededed] font-semibold text-lg">Digibase</span>
          </Link>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden p-1 text-[#a1a1a1] hover:text-white"
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
                className={`flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 animate-slideIn ${
                  active
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
        <div className="p-3 border-t border-[#2a2a2a]">
          <div className="flex items-center gap-3 px-3 py-2 rounded-md bg-[#2a2a2a]/50">
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] flex items-center justify-center">
              <span className="text-white text-sm font-medium">
                {user?.name?.charAt(0).toUpperCase()}
              </span>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-[#ededed] truncate">{user?.name}</p>
              <p className="text-xs text-[#6b6b6b] truncate">{user?.email}</p>
            </div>
          </div>
          <button
            onClick={logout}
            className="w-full mt-2 flex items-center gap-3 px-3 py-2 rounded-md text-sm text-[#a1a1a1] hover:bg-[#2a2a2a]/50 hover:text-white transition-all duration-200"
          >
            <ArrowRightOnRectangleIcon className="w-5 h-5" />
            Sign out
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top bar */}
        <header className="h-14 bg-[#171717] border-b border-[#2a2a2a] flex items-center px-4 lg:px-6">
          <button
            onClick={() => setSidebarOpen(true)}
            className="lg:hidden p-2 -ml-2 text-[#a1a1a1] hover:text-white"
          >
            <Bars3Icon className="w-5 h-5" />
          </button>

          <div className="flex-1" />

          <div className="flex items-center gap-3">
            <button className="p-2 text-[#a1a1a1] hover:text-white hover:bg-[#2a2a2a] rounded-md transition-all duration-200">
              <Cog6ToothIcon className="w-5 h-5" />
            </button>
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
