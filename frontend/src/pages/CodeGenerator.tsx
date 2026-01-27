import { useState, useEffect } from 'react';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
    CommandLineIcon,
    CubeIcon,
    CodeBracketIcon,
    DocumentDuplicateIcon,
    CheckIcon,
    ArrowPathIcon,
    GlobeAltIcon,
    RectangleStackIcon
} from '@heroicons/react/24/outline';

interface DynamicModel {
    id: number;
    name: string;
    display_name: string;
    table_name: string;
}

interface GeneratedFile {
    name: string;
    code: string;
    description: string;
}

export function CodeGenerator() {
    const [models, setModels] = useState<DynamicModel[]>([]);
    const [selectedModelId, setSelectedModelId] = useState<string>('');
    const [framework, setFramework] = useState<'react' | 'vue'>('react');
    const [operation, setOperation] = useState<'all' | 'list' | 'create' | 'hook'>('all');
    const [generatedFiles, setGeneratedFiles] = useState<GeneratedFile[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isFetchingModels, setIsFetchingModels] = useState(true);
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);

    useEffect(() => {
        const fetchModels = async () => {
            try {
                const response = await api.get('/models');
                const data = response.data.data || response.data || [];
                setModels(data);
                if (data.length > 0) {
                    setSelectedModelId(data[0].id.toString());
                }
            } catch (err) {
                console.error('Failed to fetch models', err);
            } finally {
                setIsFetchingModels(false);
            }
        };
        fetchModels();
    }, []);

    const handleGenerate = async () => {
        if (!selectedModelId) return;
        setIsLoading(true);
        try {
            const response = await api.post('/code/generate', {
                model_id: selectedModelId,
                framework,
                operation,
                style: 'tailwind',
                typescript: true
            });
            setGeneratedFiles(response.data.files || []);
        } catch (err) {
            console.error('Generation failed', err);
        } finally {
            setIsLoading(false);
        }
    };

    const copyToClipboard = (code: string, index: number) => {
        navigator.clipboard.writeText(code);
        setCopiedIndex(index);
        setTimeout(() => setCopiedIndex(null), 2000);
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-7xl mx-auto">
                <div className="mb-8 animate-slideUp">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                            <CodeBracketIcon className="w-6 h-6 text-blue-400" />
                        </div>
                        <h1 className="text-2xl font-semibold text-[#ededed]">Code Generator</h1>
                    </div>
                    <p className="text-[#a1a1a1] max-w-2xl">
                        Generate production-ready frontend components for your models.
                        Copy and paste directly into your project!
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
                    {/* Configuration Panel */}
                    <div className="lg:col-span-1 space-y-6 animate-slideUp" style={{ animationDelay: '100ms' }}>
                        <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl p-5 shadow-sm">
                            <label className="text-sm font-medium text-[#ededed] mb-4 flex items-center gap-2">
                                <CubeIcon className="w-4 h-4 text-[#3ecf8e]" />
                                Select Model
                            </label>
                            <select
                                className="w-full bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                value={selectedModelId}
                                onChange={(e) => setSelectedModelId(e.target.value)}
                                disabled={isFetchingModels}
                            >
                                {isFetchingModels ? (
                                    <option>Loading models...</option>
                                ) : (
                                    models.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.display_name}
                                        </option>
                                    ))
                                )}
                            </select>

                            <label className="text-sm font-medium text-[#ededed] mt-6 mb-4 flex items-center gap-2">
                                <CommandLineIcon className="w-4 h-4 text-blue-400" />
                                Framework
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    onClick={() => setFramework('react')}
                                    className={`flex flex-col items-center gap-2 p-3 rounded-lg border transition-all ${framework === 'react'
                                        ? 'bg-blue-500/10 border-blue-500 text-blue-400'
                                        : 'bg-[#323232] border-transparent text-[#a1a1a1] hover:border-[#3a3a3a]'
                                        }`}
                                >
                                    <GlobeAltIcon className="w-6 h-6" />
                                    <span className="text-xs font-semibold">React</span>
                                </button>
                                <button
                                    onClick={() => setFramework('vue')}
                                    className={`flex flex-col items-center gap-2 p-3 rounded-lg border transition-all ${framework === 'vue'
                                        ? 'bg-[#3ecf8e]/10 border-[#3ecf8e] text-[#3ecf8e]'
                                        : 'bg-[#323232] border-transparent text-[#a1a1a1] hover:border-[#3a3a3a]'
                                        }`}
                                >
                                    <RectangleStackIcon className="w-6 h-6" />
                                    <span className="text-xs font-semibold">Vue 3</span>
                                </button>
                            </div>

                            <label className="text-sm font-medium text-[#ededed] mt-6 mb-4 flex items-center gap-2">
                                <RectangleStackIcon className="w-4 h-4 text-orange-400" />
                                Operation
                            </label>
                            <div className="space-y-2">
                                {[
                                    { id: 'all', label: 'All-in-One Bundle' },
                                    { id: 'list', label: 'Data Table (List)' },
                                    { id: 'create', label: 'Entry Form (Create)' },
                                    { id: 'hook', label: 'CRUD Hook (API)' },
                                ].map((op) => (
                                    <button
                                        key={op.id}
                                        onClick={() => setOperation(op.id as any)}
                                        className={`w-full text-left px-3 py-2 rounded-md text-sm transition-all ${operation === op.id
                                            ? 'bg-[#3a3a3a] text-white border-l-2 border-[#3ecf8e]'
                                            : 'text-[#a1a1a1] hover:bg-[#323232]'
                                            }`}
                                    >
                                        {op.label}
                                    </button>
                                ))}
                            </div>

                            <button
                                onClick={handleGenerate}
                                disabled={isLoading || !selectedModelId}
                                className="w-full mt-8 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-semibold py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50"
                            >
                                {isLoading ? (
                                    <>
                                        <ArrowPathIcon className="w-5 h-5 animate-spin" />
                                        Generating...
                                    </>
                                ) : (
                                    <>
                                        <CommandLineIcon className="w-5 h-5" />
                                        Generate Code
                                    </>
                                )}
                            </button>
                        </div>

                        <div className="bg-indigo-500/5 border border-indigo-500/10 rounded-xl p-4">
                            <h3 className="text-xs font-semibold text-indigo-400 uppercase tracking-wider mb-2">Pro Tip</h3>
                            <p className="text-xs text-[#8a8a8a] leading-relaxed">
                                These components use Tailwind CSS and Axios. Make sure you have them installed in your frontend project.
                            </p>
                        </div>
                    </div>

                    {/* Output Panel */}
                    <div className="lg:col-span-3 space-y-6 animate-slideUp" style={{ animationDelay: '200ms' }}>
                        {generatedFiles.length === 0 ? (
                            <div className="h-[500px] bg-[#2a2a2a] border-2 border-dashed border-[#3a3a3a] rounded-2xl flex flex-col items-center justify-center text-center p-8">
                                <CommandLineIcon className="w-16 h-16 text-[#3a3a3a] mb-4" />
                                <h3 className="text-xl font-medium text-[#6b6b6b]">Ready to generate?</h3>
                                <p className="text-[#555] max-w-sm mt-2">
                                    Select a model and framework on the left, then click Generate to see your code snippets here.
                                </p>
                            </div>
                        ) : (
                            generatedFiles.map((file, idx) => (
                                <div key={idx} className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl overflow-hidden shadow-xl">
                                    <div className="bg-[#323232]/50 px-6 py-4 border-b border-[#3a3a3a] flex items-center justify-between">
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <DocumentDuplicateIcon className="w-4 h-4 text-blue-400" />
                                                <h2 className="text-sm font-mono font-bold text-[#ededed]">{file.name}</h2>
                                            </div>
                                            <p className="text-xs text-[#a1a1a1] italic">{file.description}</p>
                                        </div>
                                        <button
                                            onClick={() => copyToClipboard(file.code, idx)}
                                            className="flex items-center gap-2 px-3 py-1.5 bg-[#404040] hover:bg-[#505050] text-[#ededed] text-xs font-medium rounded-md transition-all"
                                        >
                                            {copiedIndex === idx ? (
                                                <>
                                                    <CheckIcon className="w-4 h-4 text-[#3ecf8e]" />
                                                    Copied!
                                                </>
                                            ) : (
                                                <>
                                                    <DocumentDuplicateIcon className="w-4 h-4" />
                                                    Copy Code
                                                </>
                                            )}
                                        </button>
                                    </div>
                                    <div className="relative group">
                                        <pre className="p-6 overflow-x-auto text-sm font-mono text-[#d1d1d1] leading-relaxed bg-[#1c1c1c]">
                                            <code>{file.code}</code>
                                        </pre>
                                        <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span className="text-[10px] bg-white/5 text-white/20 px-2 py-1 rounded">ReadOnly</span>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </Layout>
    );
}
