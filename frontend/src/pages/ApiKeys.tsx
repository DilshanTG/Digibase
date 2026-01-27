import { useState, useEffect } from 'react';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
    KeyIcon,
    PlusIcon,
    TrashIcon,
    DocumentDuplicateIcon,
    CheckIcon,
    ClockIcon,
    ShieldCheckIcon,
    XMarkIcon,
    ArrowPathIcon
} from '@heroicons/react/24/outline';

interface ApiKey {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
    expires_at: string | null;
}

export function ApiKeys() {
    const [keys, setKeys] = useState<ApiKey[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');

    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [newKeyName, setNewKeyName] = useState('');
    const [generatedToken, setGeneratedToken] = useState<string | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [copied, setCopied] = useState(false);

    const fetchKeys = async () => {
        try {
            setIsLoading(true);
            const response = await api.get('/tokens');
            setKeys(response.data);
        } catch (err: any) {
            setError('Failed to load API keys');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchKeys();
    }, []);

    const handleCreate = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsGenerating(true);
        setError('');

        try {
            const response = await api.post('/tokens', { name: newKeyName });
            setGeneratedToken(response.data.token);
            fetchKeys();
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to generate key');
        } finally {
            setIsGenerating(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to revoke this API key? Applications using it will stop working immediately.')) return;
        try {
            await api.delete(`/tokens/${id}`);
            setKeys(keys.filter(k => k.id !== id));
        } catch (err: any) {
            setError('Failed to revoke key');
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const closeAndReset = () => {
        setIsModalOpen(false);
        setGeneratedToken(null);
        setNewKeyName('');
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-8 animate-slideUp">
                    <div>
                        <h1 className="text-2xl font-semibold text-[#ededed] flex items-center gap-2">
                            <KeyIcon className="w-6 h-6 text-[#3ecf8e]" />
                            API Keys
                        </h1>
                        <p className="text-[#a1a1a1] mt-1">Manage personal access tokens for project authentication</p>
                    </div>
                    <button
                        onClick={() => setIsModalOpen(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md transition-all duration-200 glow-hover"
                    >
                        <PlusIcon className="w-5 h-5" />
                        Generate New Key
                    </button>
                </div>

                {/* Error */}
                {error && (
                    <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 animate-fadeIn">
                        {error}
                    </div>
                )}

                {/* Info Box */}
                <div className="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 mb-8 flex gap-4 animate-fadeIn">
                    <ShieldCheckIcon className="w-6 h-6 text-blue-400 shrink-0" />
                    <div>
                        <h4 className="text-sm font-semibold text-blue-400">Security Best Practices</h4>
                        <p className="text-xs text-[#8a8a8a] mt-1 leading-relaxed">
                            API keys provide full access to your project's data. Never commit them to version control or share them in client-side code without proper restrictions.
                            We recommend rotating keys every 90 days.
                        </p>
                    </div>
                </div>

                {/* Keys List */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-slideUp" style={{ animationDelay: '100ms' }}>
                    {isLoading ? (
                        Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl p-6 h-40 skeleton" />
                        ))
                    ) : keys.length === 0 ? (
                        <div className="col-span-full py-12 flex flex-col items-center justify-center text-[#6b6b6b] bg-[#2a2a2a] border-2 border-dashed border-[#3a3a3a] rounded-2xl">
                            <KeyIcon className="w-12 h-12 mb-4 opacity-20" />
                            <p>No API keys generated yet</p>
                        </div>
                    ) : (
                        keys.map((key, idx) => (
                            <div key={key.id} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl p-6 hover:shadow-xl transition-all group animate-fadeIn" style={{ animationDelay: `${idx * 50}ms` }}>
                                <div className="flex justify-between items-start mb-4">
                                    <h3 className="font-semibold text-[#ededed]">{key.name}</h3>
                                    <button
                                        onClick={() => handleDelete(key.id)}
                                        className="p-1.5 text-[#6b6b6b] hover:text-red-400 hover:bg-red-400/10 rounded transition-all opacity-0 group-hover:opacity-100"
                                    >
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-xs text-[#6b6b6b]">
                                        <ClockIcon className="w-3.5 h-3.5" />
                                        <span>Created: {new Date(key.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-xs text-[#6b6b6b]">
                                        <ArrowPathIcon className="w-3.5 h-3.5" />
                                        <span>Last used: {key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Never'}</span>
                                    </div>
                                </div>

                                <div className="mt-6 flex items-center gap-2 text-[10px] font-mono bg-[#1c1c1c] p-2 rounded border border-[#3a3a3a] text-[#8a8a8a]">
                                    <KeyIcon className="w-3 h-3 text-[#3ecf8e]" />
                                    <span>••••••••••••••••••••••••</span>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Generation Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fadeIn">
                    <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-2xl w-full max-w-md overflow-hidden shadow-2xl animate-slideUp">
                        {!generatedToken ? (
                            <>
                                <div className="p-6 border-b border-[#3a3a3a] flex items-center justify-between">
                                    <h3 className="text-xl font-semibold text-white">New API Key</h3>
                                    <button onClick={() => setIsModalOpen(false)} className="p-2 text-[#6b6b6b] hover:text-white">
                                        <XMarkIcon className="w-5 h-5" />
                                    </button>
                                </div>
                                <form onSubmit={handleCreate} className="p-6 space-y-4">
                                    <div>
                                        <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Key Description</label>
                                        <input
                                            type="text"
                                            required
                                            autoFocus
                                            value={newKeyName}
                                            onChange={e => setNewKeyName(e.target.value)}
                                            className="w-full bg-[#1c1c1c] border border-[#3a3a3a] text-[#ededed] rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                            placeholder="e.g. Production Web App"
                                        />
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={isGenerating}
                                        className="w-full py-3 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-semibold rounded-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                    >
                                        {isGenerating ? <ArrowPathIcon className="w-5 h-5 animate-spin" /> : 'Generate Secret Key'}
                                    </button>
                                </form>
                            </>
                        ) : (
                            <div className="p-8 text-center">
                                <div className="w-16 h-16 bg-[#3ecf8e]/10 text-[#3ecf8e] rounded-full flex items-center justify-center mx-auto mb-6">
                                    <CheckIcon className="w-8 h-8" />
                                </div>
                                <h3 className="text-xl font-bold text-white mb-2">Key Generated!</h3>
                                <p className="text-sm text-[#a1a1a1] mb-8">
                                    Please copy your key now. For security reasons, <span className="text-[#3ecf8e] font-semibold">you won't be able to see it again.</span>
                                </p>

                                <div className="relative group mb-8">
                                    <div className="bg-[#1c1c1c] border border-[#3a3a3a] rounded-xl p-4 font-mono text-sm break-all text-[#3ecf8e]">
                                        {generatedToken}
                                    </div>
                                    <button
                                        onClick={() => copyToClipboard(generatedToken)}
                                        className="absolute -top-3 -right-3 p-2 bg-[#3a3a3a] text-white rounded-lg shadow-lg hover:bg-[#4a4a4a] transition-all"
                                    >
                                        {copied ? <CheckIcon className="w-4 h-4" /> : <DocumentDuplicateIcon className="w-4 h-4" />}
                                    </button>
                                </div>

                                <button
                                    onClick={closeAndReset}
                                    className="w-full py-3 border border-[#3a3a3a] text-[#ededed] hover:bg-[#323232] font-semibold rounded-lg transition-all"
                                >
                                    I've stored it safely
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </Layout>
    );
}
