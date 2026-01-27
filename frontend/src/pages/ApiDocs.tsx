import { useState, useEffect } from 'react';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
    CommandLineIcon,
    CubeIcon,
    KeyIcon,
    FolderIcon,
    DocumentDuplicateIcon,
    CheckIcon
} from '@heroicons/react/24/outline';

interface DynamicModel {
    id: number;
    name: string;
    table_name: string;
    display_name: string;
}

interface Endpoint {
    method: 'GET' | 'POST' | 'PUT' | 'DELETE';
    path: string;
    description: string;
    params?: string[];
}

interface ApiSection {
    title: string;
    icon: any;
    description: string;
    endpoints: Endpoint[];
}

export function ApiDocs() {
    const [models, setModels] = useState<DynamicModel[]>([]);
    const [copiedSnippet, setCopiedSnippet] = useState<string | null>(null);

    useEffect(() => {
        const fetchModels = async () => {
            try {
                const response = await api.get('/models');
                setModels(response.data.data || response.data || []);
            } catch (error) {
                console.error('Failed to fetch models', error);
            }
        };
        fetchModels();
    }, []);

    const copyToClipboard = async (text: string, id: string) => {
        await navigator.clipboard.writeText(text);
        setCopiedSnippet(id);
        setTimeout(() => setCopiedSnippet(null), 2000);
    };

    const authEndpoints: Endpoint[] = [
        { method: 'POST', path: '/api/register', description: 'Register a new user', params: ['name', 'email', 'password', 'password_confirmation'] },
        { method: 'POST', path: '/api/login', description: 'Login and receive API token', params: ['email', 'password', 'device_name?'] },
        { method: 'POST', path: '/api/logout', description: 'Invalidate current token' },
        { method: 'GET', path: '/api/user', description: 'Get authenticated user details' },
    ];

    const storageEndpoints: Endpoint[] = [
        { method: 'GET', path: '/api/storage', description: 'List uploaded files' },
        { method: 'POST', path: '/api/storage', description: 'Upload a file', params: ['file', 'is_public', 'folder'] },
        { method: 'GET', path: '/api/storage/{id}', description: 'Get file details' },
        { method: 'DELETE', path: '/api/storage/{id}', description: 'Delete a file' },
    ];

    // Generate model endpoints dynamically
    const modelSections: ApiSection[] = models.map(model => ({
        title: model.display_name,
        icon: CubeIcon,
        description: `CRUD endpoints for ${model.display_name}`,
        endpoints: [
            { method: 'GET', path: `/api/data/${model.table_name}`, description: `List all ${model.display_name} records` },
            { method: 'POST', path: `/api/data/${model.table_name}`, description: `Create a new ${model.display_name}` },
            { method: 'GET', path: `/api/data/${model.table_name}/{id}`, description: `Get a specific ${model.display_name}` },
            { method: 'PUT', path: `/api/data/${model.table_name}/{id}`, description: `Update a ${model.display_name}` },
            { method: 'DELETE', path: `/api/data/${model.table_name}/{id}`, description: `Delete a ${model.display_name}` },
        ]
    }));

    const staticSections: ApiSection[] = [
        {
            title: 'Authentication',
            icon: KeyIcon,
            description: 'User management and token generation',
            endpoints: authEndpoints
        },
        {
            title: 'Storage',
            icon: FolderIcon,
            description: 'File upload and management',
            endpoints: storageEndpoints
        }
    ];

    const allSections = [...staticSections, ...modelSections];

    const MethodBadge = ({ method }: { method: string }) => {
        const colors = {
            GET: 'bg-[#3ecf8e]/10 text-[#3ecf8e]',
            POST: 'bg-blue-500/10 text-blue-400',
            PUT: 'bg-yellow-500/10 text-yellow-400',
            DELETE: 'bg-red-500/10 text-red-400'
        };
        return (
            <span className={`px-2 py-1 rounded text-xs font-bold ${colors[method as keyof typeof colors]}`}>
                {method}
            </span>
        );
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-7xl mx-auto">
                <div className="mb-8 animate-slideUp">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500/20 to-teal-500/20 flex items-center justify-center">
                            <CommandLineIcon className="w-6 h-6 text-indigo-400" />
                        </div>
                        <h1 className="text-2xl font-semibold text-[#ededed]">API Documentation</h1>
                    </div>
                    <p className="text-[#a1a1a1] max-w-2xl">
                        Complete reference for your application's generated APIs.
                        All endpoints are accessible relative to your base URL.
                        Use your Bearer token for authentication.
                    </p>
                </div>

                <div className="space-y-8 animate-fadeIn">
                    {allSections.map((section, idx) => (
                        <div key={idx} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl overflow-hidden">
                            <div className="p-6 border-b border-[#3a3a3a] bg-[#323232]/50">
                                <div className="flex items-center gap-2 mb-1">
                                    <section.icon className="w-5 h-5 text-[#3ecf8e]" />
                                    <h2 className="text-lg font-medium text-[#ededed]">{section.title}</h2>
                                </div>
                                <p className="text-sm text-[#6b6b6b]">{section.description}</p>
                            </div>

                            <div className="divide-y divide-[#3a3a3a]">
                                {section.endpoints.map((endpoint, eIdx) => (
                                    <div key={eIdx} className="p-4 hover:bg-[#323232] transition-colors group">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-3">
                                                <MethodBadge method={endpoint.method} />
                                                <code className="text-sm font-mono text-[#ededed]">{endpoint.path}</code>
                                            </div>
                                            <button
                                                onClick={() => copyToClipboard(endpoint.path, `${idx}-${eIdx}`)}
                                                className="p-1.5 text-[#6b6b6b] hover:text-[#ededed] opacity-0 group-hover:opacity-100 transition-all"
                                                title="Copy endpoint"
                                            >
                                                {copiedSnippet === `${idx}-${eIdx}` ?
                                                    <CheckIcon className="w-4 h-4 text-[#3ecf8e]" /> :
                                                    <DocumentDuplicateIcon className="w-4 h-4" />
                                                }
                                            </button>
                                        </div>
                                        <p className="text-sm text-[#a1a1a1] ml-[52px]">{endpoint.description}</p>

                                        {endpoint.params && (
                                            <div className="ml-[52px] mt-2 flex flex-wrap gap-2">
                                                {endpoint.params.map(param => (
                                                    <span key={param} className="px-1.5 py-0.5 rounded text-[10px] bg-[#1c1c1c] text-[#6b6b6b] border border-[#3a3a3a]">
                                                        {param}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </Layout>
    );
}
