import { useState, useEffect, useRef } from 'react';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
    CommandLineIcon,
    CubeIcon,
    KeyIcon,
    FolderIcon,
    DocumentDuplicateIcon,
    CheckIcon,
    UsersIcon,
    CodeBracketIcon,
    MagnifyingGlassIcon,
    BookOpenIcon,
    PlayIcon,
    VariableIcon,
    ServerIcon,
    XMarkIcon,
    ArrowPathIcon
} from '@heroicons/react/24/outline';

interface DynamicModel {
    id: number;
    name: string;
    table_name: string;
    display_name: string;
    fields: any[];
}

interface Endpoint {
    id: string;
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';
    path: string;
    description: string;
    params?: { name: string; type: string; required: boolean; description: string }[];
    requestBody?: any;
    responseBody?: any;
}

interface ApiSection {
    id: string;
    title: string;
    icon: any;
    description: string;
    endpoints: Endpoint[];
}

export function ApiDocs() {
    const [models, setModels] = useState<DynamicModel[]>([]);
    const [copiedSnippet, setCopiedSnippet] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeSection, setActiveSection] = useState('intro');
    const [isTestModalOpen, setIsTestModalOpen] = useState(false);
    const [testEndpoint, setTestEndpoint] = useState<Endpoint | null>(null);
    const [testRequestBody, setTestRequestBody] = useState<string>('');
    const [testResponse, setTestResponse] = useState<any>(null);
    const [isTesting, setIsTesting] = useState(false);

    const sectionsRef = useRef<{ [key: string]: HTMLElement | null }>({});

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

    const scrollToSection = (id: string) => {
        setActiveSection(id);
        sectionsRef.current[id]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const handleTestEndpoint = async (endpoint: Endpoint) => {
        setTestEndpoint(endpoint);
        setTestRequestBody(JSON.stringify(endpoint.requestBody || {}, null, 2));
        setTestResponse(null);
        setIsTestModalOpen(true);
    };

    const runTest = async () => {
        if (!testEndpoint) return;
        setIsTesting(true);
        try {
            // Strip /api if present as baseURL already includes it
            let path = testEndpoint.path.replace('{id}', '1');
            if (path.startsWith('/api')) {
                path = path.slice(4); // Remove "/api"
            }

            const body = testRequestBody ? JSON.parse(testRequestBody) : {};

            let response;
            if (testEndpoint.method === 'GET') {
                response = await api.get(path);
            } else if (testEndpoint.method === 'POST') {
                response = await api.post(path, body);
            } else if (testEndpoint.method === 'PUT') {
                response = await api.put(path, body);
            } else if (testEndpoint.method === 'DELETE') {
                response = await api.delete(path);
            }
            setTestResponse(response?.data);
        } catch (err: any) {
            setTestResponse(err.response?.data || { error: 'Request failed', details: err.message });
        } finally {
            setIsTesting(false);
        }
    };

    const getBaseUrl = () => {
        const origin = window.location.origin;
        if (origin.includes(':5173')) {
            return origin.replace(':5173', ':8000');
        }
        return origin;
    };

    const authEndpoints: Endpoint[] = [
        {
            id: 'auth-reg', method: 'POST', path: '/api/register', description: 'Register a new account on the platform.', params: [
                { name: 'name', type: 'string', required: true, description: 'User full name' },
                { name: 'email', type: 'string', required: true, description: 'Valid unique email address' },
                { name: 'password', type: 'string', required: true, description: 'Min 8 characters' },
                { name: 'password_confirmation', type: 'string', required: true, description: 'Must match password' }
            ]
        },
        { id: 'auth-login', method: 'POST', path: '/api/login', description: 'Authenticate and receive a Bearer token.', requestBody: { email: 'admin@digibase.dev', password: 'password' } },
        { id: 'auth-user', method: 'GET', path: '/api/user', description: 'Retrieve details of the currently authenticated user.' },
        { id: 'auth-logout', method: 'POST', path: '/api/logout', description: 'Revoke the current access token.' },
    ];

    const settingsEndpoints: Endpoint[] = [
        { id: 'set-list', method: 'GET', path: '/api/settings', description: 'Retrieve all project settings grouped by category.' },
        { id: 'set-update', method: 'PUT', path: '/api/settings', description: 'Batch update multiple settings.', requestBody: { settings: [{ key: 'project_name', value: 'My App' }] } },
    ];

    const migrationEndpoints: Endpoint[] = [
        { id: 'mig-list', method: 'GET', path: '/api/migrations', description: 'Get status of all database migrations.' },
        { id: 'mig-run', method: 'POST', path: '/api/migrations/run', description: 'Execute all pending migrations.' },
        { id: 'mig-rollback', method: 'POST', path: '/api/migrations/rollback', description: 'Rollback the last migration batch.' },
    ];

    const userEndpoints: Endpoint[] = [
        { id: 'usr-list', method: 'GET', path: '/api/users', description: 'Fetch all platform users with roles. Admin only.' },
        {
            id: 'usr-create', method: 'POST', path: '/api/users', description: 'Create a new user account.', params: [
                { name: 'name', type: 'string', required: true, description: 'Full name' },
                { name: 'email', type: 'string', required: true, description: 'Unique email' },
                { name: 'role', type: 'string', required: true, description: 'Role name (e.g. admin, user)' }
            ]
        },
        { id: 'usr-update', method: 'PUT', path: '/api/users/{id}', description: 'Update user profile or change roles.' },
    ];

    const roleEndpoints: Endpoint[] = [
        { id: 'role-list', method: 'GET', path: '/api/roles', description: 'List all available security roles.' },
        { id: 'role-perm', method: 'GET', path: '/api/permissions', description: 'Fetch list of all system permissions.' },
    ];

    const storageEndpoints: Endpoint[] = [
        { id: 'st-list', method: 'GET', path: '/api/storage', description: 'List all uploaded files in your project.' },
        { id: 'st-upload', method: 'POST', path: '/api/storage', description: 'Upload a new file (Multipart/Form-Data).' },
        { id: 'st-del', method: 'DELETE', path: '/api/storage/{id}', description: 'Permanently remove a file from storage.' },
    ];

    const modelSections: ApiSection[] = models.map(model => ({
        id: `model-${model.table_name}`,
        title: `${model.display_name} API`,
        icon: CubeIcon,
        description: `Complete CRUD operations for the ${model.display_name} model.`,
        endpoints: [
            { id: `${model.name}-get`, method: 'GET', path: `/api/data/${model.table_name}`, description: `Fetch paginated list of ${model.display_name} records.` },
            { id: `${model.name}-post`, method: 'POST', path: `/api/data/${model.table_name}`, description: `Insert a new record into ${model.table_name}.`, requestBody: model.fields.reduce((acc, f) => ({ ...acc, [f.name]: f.type === 'integer' ? 0 : 'value' }), {}) },
            { id: `${model.name}-show`, method: 'GET', path: `/api/data/${model.table_name}/{id}`, description: `Retrieve a single ${model.display_name} by ID.` },
            { id: `${model.name}-put`, method: 'PUT', path: `/api/data/${model.table_name}/{id}`, description: `Update an existing ${model.display_name} record.` },
            { id: `${model.name}-del`, method: 'DELETE', path: `/api/data/${model.table_name}/{id}`, description: `Delete record from ${model.table_name}.` },
        ]
    }));

    const staticSections: ApiSection[] = [
        { id: 'auth', title: 'Authentication', icon: KeyIcon, description: 'Secure access control and token management.', endpoints: authEndpoints },
        { id: 'users', title: 'Identity & Access', icon: UsersIcon, description: 'User management, roles, and permissions.', endpoints: [...userEndpoints, ...roleEndpoints] },
        { id: 'storage', title: 'File Storage', icon: FolderIcon, description: 'Cloud storage management and uploads.', endpoints: storageEndpoints },
        { id: 'settings', title: 'Settings', icon: KeyIcon, description: 'Project configuration and environment management.', endpoints: settingsEndpoints },
        { id: 'migrations', title: 'Migrations', icon: CommandLineIcon, description: 'Database version control and schema management.', endpoints: migrationEndpoints },
        {
            id: 'utils', title: 'Utilities', icon: CodeBracketIcon, description: 'Advanced developer tools and system helpers.', endpoints: [
                { id: 'gen-code', method: 'POST', path: '/api/code/generate', description: 'Generate React/Vue source code for any model.' },
                { id: 'sql-query', method: 'POST', path: '/api/database/query', description: 'Execute Read-Only raw SQL queries.' }
            ]
        }
    ];

    const websocketSection: ApiSection = {
        id: 'realtime',
        title: 'Real-time (WebSockets)',
        icon: PlayIcon,
        description: 'Listen for live updates using Laravel Reverb.',
        endpoints: [
            { id: 'ws-activity', method: 'GET', path: 'digibase.activity', description: 'Public channel for live system activity.' },
        ]
    };

    const allSections = [...staticSections, ...modelSections, websocketSection];
    const filteredSections = allSections.filter(s =>
        s.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        s.endpoints.some(e => e.path.toLowerCase().includes(searchQuery.toLowerCase()))
    );

    const MethodBadge = ({ method }: { method: string }) => {
        const colors = {
            GET: 'bg-[#3ecf8e]/10 text-[#3ecf8e] border-[#3ecf8e]/20',
            POST: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            PUT: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
            PATCH: 'bg-orange-500/10 text-orange-400 border-orange-500/20',
            DELETE: 'bg-red-500/10 text-red-400 border-red-500/20'
        };
        return (
            <span className={`px-2 py-0.5 rounded border text-[10px] font-bold tracking-wider ${colors[method as keyof typeof colors]}`}>
                {method}
            </span>
        );
    };

    return (
        <Layout>
            <div className="flex h-[calc(100-56px)] overflow-hidden">
                {/* Sidebar Nav */}
                <aside className="w-72 bg-[#171717] border-r border-[#2a2a2a] flex flex-col hidden lg:flex">
                    <div className="p-6">
                        <div className="relative mb-6">
                            <MagnifyingGlassIcon className="w-4 h-4 text-[#6b6b6b] absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search API..."
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                className="w-full bg-[#1c1c1c] border border-[#2a2a2a] rounded-lg pl-9 pr-4 py-2 text-sm text-[#ededed] focus:ring-1 focus:ring-[#3ecf8e] outline-none"
                            />
                        </div>

                        <nav className="space-y-8 overflow-y-auto max-h-[calc(100vh-200px)] custom-scrollbar">
                            <div>
                                <h3 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-widest mb-4">Introduction</h3>
                                <button
                                    onClick={() => scrollToSection('intro')}
                                    className={`flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-md text-sm transition-all ${activeSection === 'intro' ? 'bg-[#3ecf8e]/10 text-[#3ecf8e]' : 'text-[#a1a1a1] hover:text-white'}`}
                                >
                                    <BookOpenIcon className="w-4 h-4" />
                                    Getting Started
                                </button>
                            </div>

                            <div>
                                <h3 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-widest mb-4">Core Resources</h3>
                                <div className="space-y-1">
                                    {staticSections.map(s => (
                                        <button
                                            key={s.id}
                                            onClick={() => scrollToSection(s.id)}
                                            className={`flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-md text-sm transition-all ${activeSection === s.id ? 'bg-[#3ecf8e]/10 text-[#3ecf8e]' : 'text-[#a1a1a1] hover:text-white'}`}
                                        >
                                            <s.icon className="w-4 h-4" />
                                            {s.title}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <h3 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-widest mb-4">Auto-Generated Models</h3>
                                <div className="space-y-1">
                                    {modelSections.map(s => (
                                        <button
                                            key={s.id}
                                            onClick={() => scrollToSection(s.id)}
                                            className={`flex items-center gap-2 w-full text-left px-3 py-1.5 rounded-md text-sm transition-all ${activeSection === s.id ? 'bg-[#3ecf8e]/10 text-[#3ecf8e]' : 'text-[#a1a1a1] hover:text-white'}`}
                                        >
                                            <CubeIcon className="w-4 h-4 text-blue-400/50" />
                                            {s.title}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </nav>
                    </div>
                </aside>

                {/* Content Area */}
                <main className="flex-1 overflow-y-auto bg-[#1c1c1c] scroll-smooth custom-scrollbar">
                    <div className="max-w-4xl mx-auto p-8 lg:p-12">

                        {/* Intro Section */}
                        <section id="intro" ref={(el) => { sectionsRef.current['intro'] = el; }} className="mb-20 animate-fadeIn">
                            <div className="flex items-center gap-3 mb-4">
                                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#3ecf8e] to-blue-500 p-[1px]">
                                    <div className="w-full h-full rounded-xl bg-[#1c1c1c] flex items-center justify-center">
                                        <CommandLineIcon className="w-6 h-6 text-[#3ecf8e]" />
                                    </div>
                                </div>
                                <h1 className="text-4xl font-bold text-[#ededed]">API Reference</h1>
                            </div>
                            <p className="text-lg text-[#a1a1a1] leading-relaxed mb-8">
                                Welcome to the Digibase API documentation. Our API is organized around REST.
                                Every model you create visually in our dashboard automatically generates a set of secure, high-performance endpoints for your frontend applications.
                            </p>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                                <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl p-6 hover:border-[#3ecf8e]/30 transition-all group">
                                    <ServerIcon className="w-8 h-8 text-[#3ecf8e] mb-4" />
                                    <h3 className="text-[#ededed] font-semibold mb-2">Base URL</h3>
                                    <div className="flex items-center justify-between bg-[#1c1c1c] rounded-lg px-3 py-2 border border-[#3a3a3a]">
                                        <code className="text-sm text-[#3ecf8e]">{getBaseUrl()}/api</code>
                                        <button onClick={() => copyToClipboard(`${getBaseUrl()}/api`, 'baseurl')} className="hover:text-white text-[#6b6b6b]">
                                            {copiedSnippet === 'baseurl' ? <CheckIcon className="w-4 h-4" /> : <DocumentDuplicateIcon className="w-4 h-4" />}
                                        </button>
                                    </div>
                                </div>
                                <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl p-6 hover:border-blue-500/30 transition-all">
                                    <KeyIcon className="w-8 h-8 text-blue-400 mb-4" />
                                    <h3 className="text-[#ededed] font-semibold mb-2">Authentication</h3>
                                    <p className="text-sm text-[#a1a1a1]">Use Bearer tokens in your Authorization header for all protected requests.</p>
                                    <div className="mt-3 text-xs font-mono text-blue-400/70">Authorization: Bearer <span className="text-[#6b6b6b]">&lt;token&gt;</span></div>
                                </div>
                            </div>
                        </section>

                        {/* API Sections */}
                        <div className="space-y-32">
                            {filteredSections.map((section) => (
                                <section
                                    key={section.id}
                                    id={section.id}
                                    ref={(el) => { sectionsRef.current[section.id] = el; }}
                                    className="animate-slideUp"
                                >
                                    <div className="flex items-center gap-3 mb-2">
                                        <section.icon className="w-6 h-6 text-[#3ecf8e]" />
                                        <h2 className="text-2xl font-bold text-[#ededed]">{section.title}</h2>
                                    </div>
                                    <p className="text-[#a1a1a1] mb-8">{section.description}</p>

                                    <div className="space-y-8">
                                        {section.endpoints.map((endpoint) => (
                                            <div key={endpoint.id} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all group/card">
                                                <div className="p-6">
                                                    <div className="flex items-center justify-between mb-4">
                                                        <div className="flex items-center gap-4">
                                                            <MethodBadge method={endpoint.method} />
                                                            <code className="text-sm font-mono text-[#ededed] group-hover/card:text-[#3ecf8e] transition-colors">{endpoint.path}</code>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <button
                                                                onClick={() => handleTestEndpoint(endpoint)}
                                                                className="flex items-center gap-1.5 px-3 py-1.5 bg-[#3ecf8e]/10 text-[#3ecf8e] rounded-lg text-xs font-semibold hover:bg-[#3ecf8e] hover:text-black transition-all"
                                                            >
                                                                <PlayIcon className="w-3.5 h-3.5" />
                                                                Try it
                                                            </button>
                                                            <button
                                                                onClick={() => copyToClipboard(endpoint.path, endpoint.id)}
                                                                className="p-1.5 text-[#6b6b6b] hover:text-white rounded-lg transition-all"
                                                            >
                                                                {copiedSnippet === endpoint.id ? <CheckIcon className="w-4 h-4 text-[#3ecf8e]" /> : <DocumentDuplicateIcon className="w-4 h-4" />}
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p className="text-sm text-[#a1a1a1] mb-6">{endpoint.description}</p>

                                                    {endpoint.params && (
                                                        <div className="mb-6">
                                                            <h4 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-wider mb-3">Parameters</h4>
                                                            <div className="bg-[#1c1c1c] rounded-xl border border-[#3a3a3a] overflow-hidden">
                                                                <table className="w-full text-left text-xs">
                                                                    <thead>
                                                                        <tr className="bg-[#323232]/50 border-b border-[#3a3a3a]">
                                                                            <th className="px-4 py-2 text-[#ededed]">Name</th>
                                                                            <th className="px-4 py-2 text-[#ededed]">Type</th>
                                                                            <th className="px-4 py-2 text-[#ededed]">Required</th>
                                                                            <th className="px-4 py-2 text-[#ededed]">Description</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody className="divide-y divide-[#3a3a3a]">
                                                                        {endpoint.params.map(p => (
                                                                            <tr key={p.name} className="hover:bg-white/[0.02]">
                                                                                <td className="px-4 py-3 font-mono text-[#3ecf8e]">{p.name}</td>
                                                                                <td className="px-4 py-3 text-[#a1a1a1]">{p.type}</td>
                                                                                <td className="px-4 py-3">
                                                                                    <span className={p.required ? 'text-red-400' : 'text-[#6b6b6b]'}>{p.required ? 'Yes' : 'No'}</span>
                                                                                </td>
                                                                                <td className="px-4 py-3 text-[#6b6b6b]">{p.description}</td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {endpoint.requestBody && (
                                                        <div>
                                                            <h4 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-wider mb-3">Example Payload</h4>
                                                            <pre className="bg-[#1c1c1c] rounded-xl p-4 border border-[#3a3a3a] text-xs text-blue-400 overflow-x-auto">
                                                                {JSON.stringify(endpoint.requestBody, null, 2)}
                                                            </pre>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                    </div>
                </main>
            </div>

            {/* Try It Out Modal (Playground) */}
            {isTestModalOpen && testEndpoint && (
                <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fadeIn">
                    <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-2xl w-full max-w-4xl h-[80vh] flex flex-col shadow-2xl animate-slideUp">
                        <div className="p-6 border-b border-[#3a3a3a] flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <PlayIcon className="w-5 h-5 text-[#3ecf8e]" />
                                <h3 className="text-xl font-bold text-white">API Playground</h3>
                            </div>
                            <button onClick={() => setIsTestModalOpen(false)} className="p-2 text-[#6b6b6b] hover:text-white transition-all">
                                <XMarkIcon className="w-6 h-6" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-hidden flex flex-col lg:flex-row">
                            {/* Request Pane */}
                            <div className="flex-1 p-6 border-b lg:border-b-0 lg:border-r border-[#3a3a3a] overflow-y-auto custom-scrollbar">
                                <h4 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-widest mb-4">Request</h4>
                                <div className="flex items-center gap-2 mb-4">
                                    <MethodBadge method={testEndpoint.method} />
                                    <code className="text-sm text-[#ededed] font-mono">{testEndpoint.path}</code>
                                </div>

                                <div className="space-y-6">
                                    <div>
                                        <label className="block text-xs font-semibold text-[#6b6b6b] mb-2 uppercase">Headers</label>
                                        <div className="bg-[#1c1c1c] rounded-lg p-3 border border-[#3a3a3a] space-y-2">
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="text-[#a1a1a1]">Authorization</span>
                                                <span className="text-[#3ecf8e]">Bearer YOUR_TOKEN</span>
                                            </div>
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="text-[#a1a1a1]">Accept</span>
                                                <span className="text-[#ededed]">application/json</span>
                                            </div>
                                        </div>
                                    </div>

                                    {testEndpoint.requestBody && (
                                        <div>
                                            <label className="block text-xs font-semibold text-[#6b6b6b] mb-2 uppercase">Body (JSON)</label>
                                            <textarea
                                                className="w-full h-48 bg-[#1c1c1c] border border-[#3a3a3a] rounded-lg p-4 text-xs font-mono text-blue-400 outline-none focus:ring-1 focus:ring-[#3ecf8e]"
                                                value={testRequestBody}
                                                onChange={(e) => setTestRequestBody(e.target.value)}
                                            />
                                        </div>
                                    )}

                                    <button
                                        onClick={runTest}
                                        disabled={isTesting}
                                        className="w-full py-3 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-bold rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                    >
                                        {isTesting ? <ArrowPathIcon className="w-5 h-5 animate-spin" /> : <PlayIcon className="w-5 h-5" />}
                                        Send Request
                                    </button>
                                </div>
                            </div>

                            {/* Response Pane */}
                            <div className="flex-1 p-6 bg-[#171717] overflow-y-auto custom-scrollbar">
                                <h4 className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-widest mb-4">Response</h4>
                                {testResponse ? (
                                    <pre className="text-xs font-mono text-[#3ecf8e] leading-relaxed">
                                        {JSON.stringify(testResponse, null, 2)}
                                    </pre>
                                ) : (
                                    <div className="h-full flex flex-col items-center justify-center text-center opacity-30">
                                        <VariableIcon className="w-12 h-12 mb-2" />
                                        <p className="text-xs">Execute a request to see results</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <style>{`
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #3ecf8e; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fadeIn { animation: fadeIn 0.5s ease-out forwards; }
        .animate-slideUp { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
      `}</style>
        </Layout>
    );
}
