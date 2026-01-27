import { useState, useEffect } from 'react';
import api from '../lib/api';
import { Layout } from '../components/Layout';
import {
    Cog6ToothIcon,
    SwatchIcon,
    GlobeAltIcon,
    ShieldCheckIcon,
    EnvelopeIcon,
    CloudArrowUpIcon,
    CheckIcon,
    ArrowPathIcon,
    MoonIcon,
    SunIcon,
} from '@heroicons/react/24/outline';

interface Setting {
    id: number;
    key: string;
    value: any;
    type: string;
    group: string;
    description: string | null;
}

export const Settings = () => {
    const [settings, setSettings] = useState<Record<string, Setting[]>>({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState('general');
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);
    const [darkMode, setDarkMode] = useState(true);

    useEffect(() => {
        const savedTheme = localStorage.getItem('digibase_theme');
        if (savedTheme === 'light') {
            setDarkMode(false);
            document.documentElement.setAttribute('data-theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    }, []);

    const toggleDarkMode = () => {
        const newMode = !darkMode;
        setDarkMode(newMode);
        localStorage.setItem('digibase_theme', newMode ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', newMode ? 'dark' : 'light');
    };

    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            const response = await api.get('/settings');
            setSettings(response.data);
        } catch (error) {
            console.error('Failed to fetch settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleChange = (group: string, index: number, newValue: any) => {
        const updatedSettings = { ...settings };
        updatedSettings[group][index].value = newValue;
        setSettings(updatedSettings);
    };

    const handleSave = async () => {
        setSaving(true);
        setMessage(null);
        try {
            const allSettings = Object.values(settings).flat();
            await api.put('/settings', { settings: allSettings });
            setMessage({ type: 'success', text: 'Settings updated successfully!' });
            setTimeout(() => setMessage(null), 3000);
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to update settings.' });
        } finally {
            setSaving(false);
        }
    };

    const getIcon = (group: string) => {
        switch (group.toLowerCase()) {
            case 'general': return Cog6ToothIcon;
            case 'appearance': return SwatchIcon;
            case 'api': return GlobeAltIcon;
            case 'security': return ShieldCheckIcon;
            case 'email': return EnvelopeIcon;
            default: return Cog6ToothIcon;
        }
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-6xl mx-auto animate-fadeIn">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-2xl font-semibold" style={{ color: 'var(--text-primary)' }}>Settings</h1>
                        <p className="mt-1" style={{ color: 'var(--text-secondary)' }}>Configure your project environment and behavior</p>
                    </div>
                    <button
                        onClick={handleSave}
                        disabled={saving || loading}
                        className="flex items-center justify-center gap-2 px-6 py-2.5 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-semibold rounded-lg transition-all shadow-lg shadow-[#3ecf8e]/10 disabled:opacity-50"
                    >
                        {saving ? <ArrowPathIcon className="w-4 h-4 animate-spin" /> : <CloudArrowUpIcon className="w-5 h-5" />}
                        {saving ? 'Saving...' : 'Save All Changes'}
                    </button>
                </div>

                {message && (
                    <div className={`mb-6 p-4 rounded-xl flex items-center gap-3 animate-slideIn ${message.type === 'success' ? 'bg-[#3ecf8e]/10 text-[#3ecf8e] border border-[#3ecf8e]/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'
                        }`}>
                        {message.type === 'success' ? <CheckIcon className="w-5 h-5" /> : <ShieldCheckIcon className="w-5 h-5" />}
                        <p className="text-sm font-medium">{message.text}</p>
                    </div>
                )}

                {loading ? (
                    <div className="flex items-center justify-center h-64 text-[#a1a1a1]">
                        <ArrowPathIcon className="w-8 h-8 animate-spin" />
                    </div>
                ) : (
                    <div className="flex flex-col lg:flex-row gap-8">
                        {/* Navigation Tabs */}
                        <div className="w-full lg:w-64 flex flex-col gap-1">
                            {Object.keys(settings).map((group) => {
                                const Icon = getIcon(group);
                                return (
                                    <button
                                        key={group}
                                        onClick={() => setActiveTab(group)}
                                        className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all`}
                                        style={{
                                            backgroundColor: activeTab === group ? 'var(--bg-secondary)' : 'transparent',
                                            color: activeTab === group ? 'var(--text-primary)' : 'var(--text-secondary)',
                                            border: activeTab === group ? '1px solid var(--border-color)' : '1px solid transparent'
                                        }}
                                    >
                                        <Icon className={`w-5 h-5 ${activeTab === group ? 'text-[#3ecf8e]' : ''}`} />
                                        <span className="capitalize">{group}</span>
                                    </button>
                                );
                            })}

                            {/* Dark Mode Toggle */}
                            <div className="mt-4 pt-4" style={{ borderTop: '1px solid var(--border-color)' }}>
                                <button
                                    onClick={toggleDarkMode}
                                    className="flex items-center justify-between w-full px-4 py-3 rounded-lg text-sm font-medium transition-all"
                                    style={{ color: 'var(--text-secondary)' }}
                                >
                                    <span className="flex items-center gap-3">
                                        {darkMode ? <MoonIcon className="w-5 h-5 text-blue-400" /> : <SunIcon className="w-5 h-5 text-yellow-400" />}
                                        <span>Theme</span>
                                    </span>
                                    <span className={`px-2 py-0.5 rounded text-xs ${darkMode ? 'bg-blue-500/20 text-blue-400' : 'bg-yellow-500/20 text-yellow-400'}`}>
                                        {darkMode ? 'Dark' : 'Light'}
                                    </span>
                                </button>
                            </div>
                        </div>

                        {/* Config Content */}
                        <div className="flex-1 space-y-6">
                            <div className="rounded-xl overflow-hidden shadow-xl" style={{ backgroundColor: 'var(--bg-secondary)', border: '1px solid var(--border-color)' }}>
                                <div className="px-6 py-4" style={{ borderBottom: '1px solid var(--border-color)', backgroundColor: 'var(--bg-tertiary)' }}>
                                    <h3 className="text-lg font-medium capitalize" style={{ color: 'var(--text-primary)' }}>{activeTab} Configuration</h3>
                                </div>
                                <div className="p-6 space-y-8">
                                    {settings[activeTab]?.map((setting, index) => (
                                        <div key={setting.key} className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-8 last:border-0 last:pb-0" style={{ borderBottom: '1px solid var(--border-color)' }}>
                                            <div className="flex-1 max-w-md">
                                                <label className="text-sm font-semibold block mb-1 capitalize" style={{ color: 'var(--text-primary)' }}>
                                                    {setting.key.replace(/_/g, ' ')}
                                                </label>
                                                <p className="text-xs leading-relaxed" style={{ color: 'var(--text-secondary)' }}>
                                                    {setting.description || `Manage the project's ${setting.key.replace(/_/g, ' ')} value.`}
                                                </p>
                                            </div>

                                            <div className="w-full lg:w-72">
                                                {setting.type === 'boolean' ? (
                                                    <div className="flex items-center">
                                                        <button
                                                            onClick={() => handleChange(activeTab, index, !setting.value)}
                                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${setting.value ? 'bg-[#3ecf8e]' : 'bg-[#2a2a2a]'
                                                                }`}
                                                        >
                                                            <span
                                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${setting.value ? 'translate-x-6' : 'translate-x-1'
                                                                    }`}
                                                            />
                                                        </button>
                                                        <span className="ml-3 text-xs font-medium text-[#a1a1a1]">
                                                            {setting.value ? 'Enabled' : 'Disabled'}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <div className="relative">
                                                        {setting.key.includes('color') ? (
                                                            <div className="flex items-center gap-3">
                                                                <input
                                                                    type="color"
                                                                    value={setting.value || '#000000'}
                                                                    onChange={(e) => handleChange(activeTab, index, e.target.value)}
                                                                    className="h-9 w-9 p-0 border-0 bg-transparent rounded-lg cursor-pointer overflow-hidden shadow-inner"
                                                                />
                                                                <input
                                                                    type="text"
                                                                    value={setting.value || ''}
                                                                    onChange={(e) => handleChange(activeTab, index, e.target.value)}
                                                                    className="flex-1 bg-[#1c1c1c] border border-[#3a3a3a] rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-[#3ecf8e] outline-none transition-all"
                                                                />
                                                            </div>
                                                        ) : (
                                                            <input
                                                                type="text"
                                                                value={setting.value || ''}
                                                                onChange={(e) => handleChange(activeTab, index, e.target.value)}
                                                                className="w-full bg-[#1c1c1c] border border-[#3a3a3a] rounded-lg px-4 py-2.5 text-sm text-white focus:border-[#3ecf8e] outline-none transition-all"
                                                            />
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="bg-[#3ecf8e]/5 border border-[#3ecf8e]/10 rounded-xl p-6">
                                <div className="flex gap-4">
                                    <div className="p-2 bg-[#3ecf8e]/20 rounded-lg h-fit">
                                        <ShieldCheckIcon className="w-5 h-5 text-[#3ecf8e]" />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-semibold text-white mb-1">Data Safety</h4>
                                        <p className="text-xs text-[#a1a1a1] leading-relaxed">
                                            Changes made here affect the entire Digibase environment. Ensure you verify values before saving to prevent service interruptions.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
            <style>{`
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fadeIn { animation: fadeIn 0.5s ease-out forwards; }
        .animate-slideIn { animation: slideIn 0.4s ease-out forwards; }
      `}</style>
        </Layout>
    );
};
