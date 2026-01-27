import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Layout } from '../components/Layout';
import { CardSkeleton } from '../components/Skeleton';
import api from '../lib/api';
import {
  CubeIcon,
  CircleStackIcon,
  FolderIcon,
  DocumentTextIcon,
  ArrowTrendingUpIcon,
  PlusIcon,
  ArrowRightIcon,
  ClockIcon,
} from '@heroicons/react/24/outline';

interface DashboardStats {
  models: number;
  tables: number;
  files: number;
  storage_used: string;
}

interface RecentModel {
  id: number;
  name: string;
  display_name: string;
  created_at: string;
}

export function Dashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [recentModels, setRecentModels] = useState<RecentModel[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [modelsRes, dbStatsRes, storageStatsRes] = await Promise.all([
          api.get('/models'),
          api.get('/database/stats'),
          api.get('/storage/stats'),
        ]);

        const models = modelsRes.data.data || [];
        setRecentModels(models.slice(0, 5));

        setStats({
          models: models.length,
          tables: dbStatsRes.data.total_tables || 0,
          files: storageStatsRes.data.total_files || 0,
          storage_used: formatBytes(storageStatsRes.data.total_size || 0),
        });
      } catch {
        setStats({ models: 0, tables: 0, files: 0, storage_used: '0 B' });
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
  }, []);

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  };

  const statCards = [
    {
      label: 'Total Models',
      value: stats?.models || 0,
      icon: CubeIcon,
      color: 'from-[#3ecf8e] to-[#24b47e]',
      href: '/models',
    },
    {
      label: 'Database Tables',
      value: stats?.tables || 0,
      icon: CircleStackIcon,
      color: 'from-blue-500 to-blue-600',
      href: '/database',
    },
    {
      label: 'Files Stored',
      value: stats?.files || 0,
      icon: FolderIcon,
      color: 'from-purple-500 to-purple-600',
      href: '/storage',
    },
    {
      label: 'Storage Used',
      value: stats?.storage_used || '0 B',
      icon: ArrowTrendingUpIcon,
      color: 'from-orange-500 to-orange-600',
      href: '/storage',
    },
  ];

  return (
    <Layout>
      <div className="p-6 lg:p-8 max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-8 animate-slideUp">
          <div>
            <h1 className="text-2xl font-semibold text-[#ededed]">Dashboard</h1>
            <p className="text-[#a1a1a1] mt-1">Welcome to your Digibase project</p>
          </div>
          <Link
            to="/models/create"
            className="flex items-center gap-2 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md transition-all duration-200 glow-hover"
          >
            <PlusIcon className="w-4 h-4" />
            New Model
          </Link>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          {isLoading
            ? Array.from({ length: 4 }).map((_, i) => <CardSkeleton key={i} />)
            : statCards.map((stat, index) => (
                <Link
                  key={stat.label}
                  to={stat.href}
                  className="group bg-[#2a2a2a] border border-[#3a3a3a] hover:border-[#4a4a4a] rounded-lg p-5 transition-all duration-200 animate-slideUp"
                  style={{ animationDelay: `${index * 50}ms` }}
                >
                  <div className="flex items-start justify-between">
                    <div
                      className={`w-10 h-10 rounded-lg bg-gradient-to-br ${stat.color} flex items-center justify-center`}
                    >
                      <stat.icon className="w-5 h-5 text-white" />
                    </div>
                    <ArrowRightIcon className="w-4 h-4 text-[#6b6b6b] group-hover:text-[#a1a1a1] group-hover:translate-x-1 transition-all duration-200" />
                  </div>
                  <div className="mt-4">
                    <p className="text-[#6b6b6b] text-sm">{stat.label}</p>
                    <p className="text-2xl font-semibold text-[#ededed] mt-1">{stat.value}</p>
                  </div>
                </Link>
              ))}
        </div>

        {/* Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Recent Models */}
          <div className="lg:col-span-2 bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg animate-slideUp" style={{ animationDelay: '200ms' }}>
            <div className="flex items-center justify-between p-4 border-b border-[#3a3a3a]">
              <h2 className="font-medium text-[#ededed] flex items-center gap-2">
                <CubeIcon className="w-5 h-5 text-[#3ecf8e]" />
                Recent Models
              </h2>
              <Link
                to="/models"
                className="text-sm text-[#3ecf8e] hover:text-[#24b47e] transition-colors"
              >
                View all
              </Link>
            </div>
            <div className="divide-y divide-[#3a3a3a]">
              {isLoading ? (
                <div className="p-4 space-y-3">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <div key={i} className="skeleton h-12 rounded-md" />
                  ))}
                </div>
              ) : recentModels.length === 0 ? (
                <div className="p-8 text-center">
                  <CubeIcon className="w-12 h-12 text-[#3a3a3a] mx-auto mb-3" />
                  <p className="text-[#6b6b6b]">No models yet</p>
                  <Link
                    to="/models/create"
                    className="inline-flex items-center gap-2 mt-3 text-sm text-[#3ecf8e] hover:text-[#24b47e]"
                  >
                    <PlusIcon className="w-4 h-4" />
                    Create your first model
                  </Link>
                </div>
              ) : (
                recentModels.map((model, index) => (
                  <Link
                    key={model.id}
                    to={`/models/${model.id}`}
                    className="flex items-center gap-4 p-4 hover:bg-[#323232] transition-colors animate-slideIn"
                    style={{ animationDelay: `${index * 50}ms` }}
                  >
                    <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#3ecf8e]/20 to-[#24b47e]/20 flex items-center justify-center">
                      <DocumentTextIcon className="w-5 h-5 text-[#3ecf8e]" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-[#ededed] truncate">{model.display_name}</p>
                      <p className="text-sm text-[#6b6b6b]">{model.name}</p>
                    </div>
                    <div className="flex items-center gap-1 text-xs text-[#6b6b6b]">
                      <ClockIcon className="w-3.5 h-3.5" />
                      {formatDate(model.created_at)}
                    </div>
                  </Link>
                ))
              )}
            </div>
          </div>

          {/* Quick Actions */}
          <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg animate-slideUp" style={{ animationDelay: '250ms' }}>
            <div className="p-4 border-b border-[#3a3a3a]">
              <h2 className="font-medium text-[#ededed]">Quick Actions</h2>
            </div>
            <div className="p-4 space-y-2">
              <Link
                to="/models/create"
                className="flex items-center gap-3 p-3 rounded-md text-[#a1a1a1] hover:text-white hover:bg-[#323232] transition-all duration-200"
              >
                <div className="w-8 h-8 rounded-md bg-[#3ecf8e]/10 flex items-center justify-center">
                  <PlusIcon className="w-4 h-4 text-[#3ecf8e]" />
                </div>
                <span className="text-sm font-medium">Create Model</span>
              </Link>
              <Link
                to="/database"
                className="flex items-center gap-3 p-3 rounded-md text-[#a1a1a1] hover:text-white hover:bg-[#323232] transition-all duration-200"
              >
                <div className="w-8 h-8 rounded-md bg-blue-500/10 flex items-center justify-center">
                  <CircleStackIcon className="w-4 h-4 text-blue-500" />
                </div>
                <span className="text-sm font-medium">Database Explorer</span>
              </Link>
              <Link
                to="/storage"
                className="flex items-center gap-3 p-3 rounded-md text-[#a1a1a1] hover:text-white hover:bg-[#323232] transition-all duration-200"
              >
                <div className="w-8 h-8 rounded-md bg-purple-500/10 flex items-center justify-center">
                  <FolderIcon className="w-4 h-4 text-purple-500" />
                </div>
                <span className="text-sm font-medium">File Storage</span>
              </Link>
            </div>

            {/* API Status */}
            <div className="p-4 border-t border-[#3a3a3a]">
              <div className="flex items-center justify-between">
                <span className="text-sm text-[#6b6b6b]">API Status</span>
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-[#3ecf8e] animate-pulse" />
                  <span className="text-sm text-[#3ecf8e]">Operational</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}
