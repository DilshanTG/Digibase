import { useState, useEffect } from 'react';
import api from '../lib/api';
import { Layout } from '../components/Layout';
import {
    ArrowPathIcon,
    CheckCircleIcon,
    ClockIcon,
    CommandLineIcon,
    XCircleIcon,
    TrashIcon
} from '@heroicons/react/24/outline';

interface Migration {
    name: string;
    status: 'ran' | 'pending';
    batch: number | null;
    created_at: string | null;
}

export const Migrations = () => {
    const [migrations, setMigrations] = useState<Migration[]>([]);
    const [loading, setLoading] = useState(true);
    const [running, setRunning] = useState(false);
    const [output, setOutput] = useState<string | null>(null);

    useEffect(() => {
        fetchMigrations();
    }, []);

    const fetchMigrations = async () => {
        setLoading(true);
        try {
            const response = await api.get('/migrations');
            setMigrations(response.data.data);
        } catch (error) {
            console.error('Failed to fetch migrations:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleMigrate = async () => {
        if (!confirm('Are you sure you want to run pending migrations?')) return;

        setRunning(true);
        setOutput(null);
        try {
            const response = await api.post('/migrations/run');
            setOutput(response.data.output);
            fetchMigrations();
        } catch (error: any) {
            setOutput(error.response?.data?.output || 'An error occurred during migration.');
        } finally {
            setRunning(false);
        }
    };

    const handleRollback = async () => {
        if (!confirm('Are you sure you want to rollback the last batch? This could delete data.')) return;

        setRunning(true);
        setOutput(null);
        try {
            const response = await api.post('/migrations/rollback');
            setOutput(response.data.output);
            fetchMigrations();
        } catch (error: any) {
            setOutput(error.response?.data?.output || 'An error occurred during rollback.');
        } finally {
            setRunning(false);
        }
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-6xl mx-auto animate-fadeIn">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-2xl font-semibold text-[#ededed] flex items-center gap-3">
                            <CommandLineIcon className="w-7 h-7 text-[#3ecf8e]" />
                            Database Migrations
                        </h1>
                        <p className="text-[#a1a1a1] mt-1">Manage and track database schema versioning</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={handleRollback}
                            disabled={running || loading}
                            className="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 rounded-lg font-medium transition-all disabled:opacity-50 flex items-center gap-2"
                        >
                            <TrashIcon className="w-4 h-4" />
                            Rollback
                        </button>
                        <button
                            onClick={handleMigrate}
                            disabled={running || loading}
                            className="px-6 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-semibold rounded-lg transition-all shadow-lg shadow-[#3ecf8e]/10 disabled:opacity-50 flex items-center gap-2"
                        >
                            {running ? <ArrowPathIcon className="w-4 h-4 animate-spin" /> : <CommandLineIcon className="w-4 h-4" />}
                            {running ? 'Running...' : 'Run Pending'}
                        </button>
                    </div>
                </div>

                {output && (
                    <div className="mb-8 bg-[#000000] border border-[#2a2a2a] rounded-xl overflow-hidden animate-slideUp">
                        <div className="px-4 py-2 bg-[#171717] border-b border-[#2a2a2a] flex items-center justify-between">
                            <span className="text-[10px] font-bold text-[#6b6b6b] uppercase tracking-widest">Execution Terminal</span>
                            <button onClick={() => setOutput(null)} className="text-[#6b6b6b] hover:text-white transition-colors">
                                <XCircleIcon className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="p-4 overflow-auto max-h-64 custom-scrollbar">
                            <pre className="font-mono text-xs text-[#3ecf8e] leading-relaxed">{output}</pre>
                        </div>
                    </div>
                )}

                <div className="bg-[#171717] border border-[#2a2a2a] rounded-xl overflow-hidden shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-[#1c1c1c] border-b border-[#2a2a2a]">
                                <tr>
                                    <th className="px-6 py-4 text-[11px] font-bold text-[#6b6b6b] uppercase tracking-wider text-center w-16">#</th>
                                    <th className="px-6 py-4 text-[11px] font-bold text-[#6b6b6b] uppercase tracking-wider">Migration Name</th>
                                    <th className="px-6 py-4 text-[11px] font-bold text-[#6b6b6b] uppercase tracking-wider w-32 text-center">Batch</th>
                                    <th className="px-6 py-4 text-[11px] font-bold text-[#6b6b6b] uppercase tracking-wider w-40">Status</th>
                                    <th className="px-6 py-4 text-[11px] font-bold text-[#6b6b6b] uppercase tracking-wider w-48 text-right pr-12">Executed At</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#2a2a2a]">
                                {loading ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center">
                                            <div className="flex items-center justify-center gap-3 text-[#6b6b6b]">
                                                <ArrowPathIcon className="w-5 h-5 animate-spin" />
                                                <span className="text-sm">Fetching migration status...</span>
                                            </div>
                                        </td>
                                    </tr>
                                ) : migrations.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-[#6b6b6b] text-sm">
                                            No migrations found in system.
                                        </td>
                                    </tr>
                                ) : (
                                    migrations.map((m, idx) => (
                                        <tr key={m.name} className="hover:bg-white/[0.02] transition-colors group">
                                            <td className="px-6 py-4 text-center">
                                                <span className="text-xs text-[#555] font-mono">{migrations.length - idx}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <span className="text-[13px] font-medium text-[#ededed] group-hover:text-[#3ecf8e] transition-colors">{m.name}</span>
                                                    <span className="text-[10px] text-[#6b6b6b] font-mono mt-0.5">.php</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {m.batch !== null ? (
                                                    <span className="px-2 py-0.5 bg-[#2a2a2a] text-[#ededed] rounded text-[10px] font-bold border border-[#3b3b3b]">
                                                        {m.batch}
                                                    </span>
                                                ) : <span className="text-[#444]">—</span>}
                                            </td>
                                            <td className="px-6 py-4">
                                                {m.status === 'ran' ? (
                                                    <div className="flex items-center gap-2 text-[#3ecf8e]">
                                                        <CheckCircleIcon className="w-4 h-4" />
                                                        <span className="text-[11px] font-bold uppercase tracking-tight">Executed</span>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center gap-2 text-[#f59e0b]">
                                                        <ClockIcon className="w-4 h-4" />
                                                        <span className="text-[11px] font-bold uppercase tracking-tight">Pending</span>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right pr-12">
                                                <span className="text-xs text-[#a1a1a1] font-medium">{m.created_at || 'Automatic System'}</span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <style>{`
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .animate-fadeIn { animation: fadeIn 0.5s ease-out forwards; }
                .animate-slideUp { animation: slideUp 0.4s ease-out forwards; }
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 10px; }
            `}</style>
        </Layout>
    );
};
