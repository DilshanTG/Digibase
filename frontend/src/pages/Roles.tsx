import { useState, useEffect } from 'react';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
    ShieldCheckIcon,
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    XMarkIcon,
    CheckIcon
} from '@heroicons/react/24/outline';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

interface Role {
    id: number;
    name: string;
    permissions: Permission[];
    users_count?: number;
}

export function Roles() {
    const [roles, setRoles] = useState<Role[]>([]);
    const [allPermissions, setAllPermissions] = useState<Permission[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');

    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);

    // Form state
    const [roleName, setRoleName] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const fetchData = async () => {
        try {
            setIsLoading(true);
            const [rolesRes, permsRes] = await Promise.all([
                api.get('/roles'),
                api.get('/permissions') // Assuming this endpoint exists based on RoleController
            ]);
            setRoles(rolesRes.data);
            setAllPermissions(permsRes.data);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to load data. You might not have permission.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    const handleOpenModal = (mode: 'create' | 'edit', role: Role | null = null) => {
        setModalMode(mode);
        setSelectedRole(role);
        if (mode === 'edit' && role) {
            setRoleName(role.name);
            setSelectedPermissions(role.permissions.map(p => p.name));
        } else {
            setRoleName('');
            setSelectedPermissions([]);
        }
        setIsModalOpen(true);
    };

    const togglePermission = (permName: string) => {
        setSelectedPermissions(prev =>
            prev.includes(permName)
                ? prev.filter(p => p !== permName)
                : [...prev, permName]
        );
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setError('');

        try {
            const payload = {
                name: roleName,
                permissions: selectedPermissions
            };

            if (modalMode === 'create') {
                await api.post('/roles', payload);
            } else if (selectedRole) {
                await api.put(`/roles/${selectedRole.id}`, payload);
            }
            setIsModalOpen(false);
            fetchData();
        } catch (err: any) {
            setError(err.response?.data?.message || 'Action failed');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this role?')) return;
        try {
            await api.delete(`/roles/${id}`);
            setRoles(roles.filter(r => r.id !== id));
        } catch (err: any) {
            setError(err.response?.data?.message || 'Delete failed');
        }
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-7xl mx-auto">
                <div className="flex justify-between items-center mb-8 animate-slideUp">
                    <div>
                        <h1 className="text-2xl font-semibold leading-tight mb-1" style={{ color: 'var(--text-primary)' }}>
                            Roles & Permissions
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-secondary)' }}>
                            Manage access control and assign capabilities to roles.
                        </p>
                    </div>
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="flex items-center gap-2 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md transition-all duration-200 glow-hover"
                    >
                        <PlusIcon className="w-5 h-5" />
                        Create Role
                    </button>
                </div>

                {error && (
                    <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 animate-fadeIn">
                        {error}
                    </div>
                )}

                {isLoading ? (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="rounded-xl border p-6 flex flex-col h-full animate-pulse" style={{ backgroundColor: 'var(--bg-secondary)', borderColor: 'var(--border-color)' }}>
                                <div className="flex gap-3 mb-4">
                                    <div className="w-10 h-10 rounded-lg" style={{ backgroundColor: 'var(--bg-tertiary)' }}></div>
                                    <div className="space-y-2 flex-1">
                                        <div className="h-4 rounded w-1/2" style={{ backgroundColor: 'var(--bg-tertiary)' }}></div>
                                        <div className="h-3 rounded w-1/4" style={{ backgroundColor: 'var(--bg-tertiary)' }}></div>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <div className="h-6 rounded w-16" style={{ backgroundColor: 'var(--bg-tertiary)' }}></div>
                                    <div className="h-6 rounded w-16" style={{ backgroundColor: 'var(--bg-tertiary)' }}></div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {roles.map((role, idx) => (
                            <div
                                key={role.id}
                                className="rounded-xl border p-6 flex flex-col h-full animate-fadeIn"
                                style={{
                                    backgroundColor: 'var(--bg-secondary)',
                                    borderColor: 'var(--border-color)',
                                    animationDelay: `${idx * 50}ms`
                                }}
                            >
                                <div className="flex justify-between items-start mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className={`p-2 rounded-lg ${role.name === 'admin' ? 'bg-purple-500/10 text-purple-400' : 'bg-blue-500/10 text-blue-400'
                                            }`}>
                                            <ShieldCheckIcon className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-lg capitalize" style={{ color: 'var(--text-primary)' }}>
                                                {role.name}
                                            </h3>
                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {role.permissions.length} permissions
                                            </p>
                                        </div>
                                    </div>
                                    {!['admin', 'user'].includes(role.name) && (
                                        <div className="flex gap-1">
                                            <button
                                                onClick={() => handleOpenModal('edit', role)}
                                                className="p-1.5 rounded hover:bg-white/5 transition-colors"
                                                style={{ color: 'var(--text-secondary)' }}
                                            >
                                                <PencilSquareIcon className="w-4 h-4" />
                                            </button>
                                            <button
                                                onClick={() => handleDelete(role.id)}
                                                className="p-1.5 rounded hover:bg-red-500/10 text-red-400 transition-colors"
                                            >
                                                <TrashIcon className="w-4 h-4" />
                                            </button>
                                        </div>
                                    )}
                                </div>

                                <div className="flex-1">
                                    <div className="flex flex-wrap gap-2 mb-4">
                                        {role.permissions.slice(0, 5).map(perm => (
                                            <span
                                                key={perm.id}
                                                className="px-2 py-1 rounded text-xs font-medium border"
                                                style={{
                                                    backgroundColor: 'var(--bg-tertiary)',
                                                    borderColor: 'var(--border-color)',
                                                    color: 'var(--text-secondary)'
                                                }}
                                            >
                                                {perm.name}
                                            </span>
                                        ))}
                                        {role.permissions.length > 5 && (
                                            <span className="px-2 py-1 rounded text-xs font-medium text-[#6b6b6b]">
                                                +{role.permissions.length - 5} more
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {['admin', 'user'].includes(role.name) && (
                                    <div className="mt-4 pt-4 border-t text-xs italic text-[#6b6b6b]" style={{ borderColor: 'var(--border-color)' }}>
                                        System role (cannot be deleted)
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4 animate-fadeIn backdrop-blur-sm">
                    <div
                        className="rounded-xl w-full max-w-2xl overflow-hidden shadow-2xl animate-slideUp"
                        style={{ backgroundColor: 'var(--bg-secondary)', border: '1px solid var(--border-color)' }}
                    >
                        <div className="flex items-center justify-between p-6 border-b" style={{ borderColor: 'var(--border-color)' }}>
                            <h3 className="text-xl font-semibold" style={{ color: 'var(--text-primary)' }}>
                                {modalMode === 'create' ? 'Create New Role' : 'Edit Role'}
                            </h3>
                            <button onClick={() => setIsModalOpen(false)} style={{ color: 'var(--text-secondary)' }}>
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="p-6">
                            <div className="mb-6">
                                <label className="block text-xs font-medium uppercase mb-2" style={{ color: 'var(--text-secondary)' }}>Role Name</label>
                                <input
                                    type="text"
                                    required
                                    value={roleName}
                                    onChange={e => setRoleName(e.target.value)}
                                    className="w-full px-3 py-2 rounded-md outline-none focus:ring-2 focus:ring-[#3ecf8e] transition-all"
                                    style={{
                                        backgroundColor: 'var(--bg-primary)',
                                        borderColor: 'var(--border-color)',
                                        color: 'var(--text-primary)',
                                        borderWidth: '1px'
                                    }}
                                    placeholder="e.g. Content Editor"
                                />
                            </div>

                            <div className="mb-6">
                                <label className="block text-xs font-medium uppercase mb-3" style={{ color: 'var(--text-secondary)' }}>Permissions</label>
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                                    {allPermissions.map(perm => {
                                        const isSelected = selectedPermissions.includes(perm.name);
                                        return (
                                            <div
                                                key={perm.id}
                                                onClick={() => togglePermission(perm.name)}
                                                className={`cursor-pointer px-3 py-2 rounded-md border flex items-center justify-between transition-all ${isSelected
                                                    ? 'bg-[#3ecf8e]/10 border-[#3ecf8e] text-[#3ecf8e]'
                                                    : 'hover:border-[#3ecf8e]/50'
                                                    }`}
                                                style={{
                                                    backgroundColor: isSelected ? undefined : 'var(--bg-tertiary)',
                                                    borderColor: isSelected ? undefined : 'var(--border-color)',
                                                    color: isSelected ? undefined : 'var(--text-secondary)'
                                                }}
                                            >
                                                <span className="text-xs font-medium truncate mr-2" title={perm.name}>{perm.name}</span>
                                                {isSelected && <CheckIcon className="w-3 h-3 flex-shrink-0" />}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setIsModalOpen(false)}
                                    className="flex-1 px-4 py-2 border rounded-md transition-all sm:text-sm font-medium hover:bg-opacity-80"
                                    style={{
                                        borderColor: 'var(--border-color)',
                                        color: 'var(--text-secondary)'
                                    }}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="flex-1 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black rounded-md transition-all sm:text-sm font-semibold flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    {isSubmitting ? 'Saving...' : (modalMode === 'create' ? 'Create Role' : 'Save Changes')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Layout>
    );
}
