import { useState, useEffect } from 'react';
import { Layout } from '../components/Layout';
import api from '../lib/api';
import {
    UsersIcon,
    UserPlusIcon,
    TrashIcon,
    PencilSquareIcon,
    ShieldCheckIcon,
    EnvelopeIcon,
    ArrowPathIcon,
    XMarkIcon
} from '@heroicons/react/24/outline';

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    created_at: string;
}

export function Users() {
    const [users, setUsers] = useState<User[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');

    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // Form state
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'user'
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const fetchUsers = async () => {
        try {
            setIsLoading(true);
            const response = await api.get('/users');
            setUsers(response.data);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to load users. You might not have permission.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchUsers();
    }, []);

    const handleOpenModal = (mode: 'create' | 'edit', user: User | null = null) => {
        setModalMode(mode);
        setSelectedUser(user);
        if (mode === 'edit' && user) {
            setFormData({
                name: user.name,
                email: user.email,
                password: '',
                password_confirmation: '',
                role: user.roles[0]?.name || 'user'
            });
        } else {
            setFormData({
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                role: 'user'
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setError('');

        try {
            if (modalMode === 'create') {
                await api.post('/users', formData);
            } else if (selectedUser) {
                // Only send password if it's provided
                const payload = { ...formData };
                if (!payload.password) {
                    delete (payload as any).password;
                    delete (payload as any).password_confirmation;
                }
                await api.put(`/users/${selectedUser.id}`, payload);
            }
            setIsModalOpen(false);
            fetchUsers();
        } catch (err: any) {
            setError(err.response?.data?.message || 'Action failed');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this user?')) return;
        try {
            await api.delete(`/users/${id}`);
            setUsers(users.filter(u => u.id !== id));
        } catch (err: any) {
            setError(err.response?.data?.message || 'Delete failed');
        }
    };

    return (
        <Layout>
            <div className="p-6 lg:p-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-8 animate-slideUp">
                    <div>
                        <h1 className="text-2xl font-semibold text-[#ededed] flex items-center gap-2">
                            <UsersIcon className="w-6 h-6 text-[#3ecf8e]" />
                            User Management
                        </h1>
                        <p className="text-[#a1a1a1] mt-1">Manage platform users and roles</p>
                    </div>
                    <button
                        onClick={() => handleOpenModal('create')}
                        className="flex items-center gap-2 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black font-medium rounded-md transition-all duration-200 glow-hover"
                    >
                        <UserPlusIcon className="w-5 h-5" />
                        Add User
                    </button>
                </div>

                {/* Error */}
                {error && (
                    <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 animate-fadeIn">
                        {error}
                    </div>
                )}

                {/* Table */}
                <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl overflow-hidden animate-slideUp" style={{ animationDelay: '100ms' }}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-[#323232]/50 border-b border-[#3a3a3a]">
                                    <th className="px-6 py-4 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wider">User</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wider">Role</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wider">Created</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#3a3a3a]">
                                {isLoading ? (
                                    Array.from({ length: 3 }).map((_, i) => (
                                        <tr key={i}>
                                            <td colSpan={4} className="px-6 py-4"><div className="skeleton h-10 w-full rounded" /></td>
                                        </tr>
                                    ))
                                ) : users.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-12 text-center text-[#6b6b6b]">No users found</td>
                                    </tr>
                                ) : (
                                    users.map((user, idx) => (
                                        <tr key={user.id} className="hover:bg-[#323232]/30 transition-colors animate-fadeIn" style={{ animationDelay: `${idx * 50}ms` }}>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-9 h-9 rounded-full bg-gradient-to-br from-[#3ecf8e]/20 to-blue-500/20 flex items-center justify-center text-[#3ecf8e] font-bold">
                                                        {user.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-[#ededed]">{user.name}</p>
                                                        <p className="text-xs text-[#6b6b6b] flex items-center gap-1">
                                                            <EnvelopeIcon className="w-3 h-3" />
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${user.roles[0]?.name === 'admin'
                                                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                    : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
                                                    }`}>
                                                    <ShieldCheckIcon className="w-3 h-3" />
                                                    {user.roles[0]?.name || 'user'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-xs text-[#a1a1a1]">
                                                {new Date(user.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        onClick={() => handleOpenModal('edit', user)}
                                                        className="p-1.5 text-[#6b6b6b] hover:text-[#3ecf8e] hover:bg-[#3ecf8e]/10 rounded transition-all"
                                                    >
                                                        <PencilSquareIcon className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(user.id)}
                                                        className="p-1.5 text-[#6b6b6b] hover:text-red-400 hover:bg-red-400/10 rounded transition-all"
                                                    >
                                                        <TrashIcon className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4 animate-fadeIn">
                    <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-xl w-full max-w-md overflow-hidden shadow-2xl animate-slideUp">
                        <div className="flex items-center justify-between p-6 border-b border-[#3a3a3a]">
                            <h3 className="text-xl font-semibold text-[#ededed]">
                                {modalMode === 'create' ? 'Add New User' : 'Edit User'}
                            </h3>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 text-[#6b6b6b] hover:text-[#ededed]">
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Full Name</label>
                                <input
                                    type="text"
                                    required
                                    value={formData.name}
                                    onChange={e => setFormData({ ...formData, name: e.target.value })}
                                    className="w-full bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                    placeholder="John Doe"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Email Address</label>
                                <input
                                    type="email"
                                    required
                                    value={formData.email}
                                    onChange={e => setFormData({ ...formData, email: e.target.value })}
                                    className="w-full bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                    placeholder="john@example.com"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Role</label>
                                <select
                                    value={formData.role}
                                    onChange={e => setFormData({ ...formData, role: e.target.value })}
                                    className="w-full bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                >
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">
                                        {modalMode === 'edit' ? 'New Password' : 'Password'}
                                    </label>
                                    <input
                                        type="password"
                                        required={modalMode === 'create'}
                                        value={formData.password}
                                        onChange={e => setFormData({ ...formData, password: e.target.value })}
                                        className="w-full bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                        placeholder="••••••••"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-[#6b6b6b] uppercase mb-1">Confirm</label>
                                    <input
                                        type="password"
                                        required={modalMode === 'create' || !!formData.password}
                                        value={formData.password_confirmation}
                                        onChange={e => setFormData({ ...formData, password_confirmation: e.target.value })}
                                        className="w-full bg-[#323232] border border-[#3a3a3a] text-[#ededed] rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-[#3ecf8e]"
                                        placeholder="••••••••"
                                    />
                                </div>
                            </div>

                            {modalMode === 'edit' && (
                                <p className="text-[10px] text-[#6b6b6b] italic">Leave password blank to keep current password.</p>
                            )}

                            <div className="flex gap-3 mt-8">
                                <button
                                    type="button"
                                    onClick={() => setIsModalOpen(false)}
                                    className="flex-1 px-4 py-2 border border-[#3a3a3a] text-[#a1a1a1] hover:bg-[#323232] rounded-md transition-all sm:text-sm font-medium"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="flex-1 px-4 py-2 bg-[#3ecf8e] hover:bg-[#24b47e] text-black rounded-md transition-all sm:text-sm font-semibold flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    {isSubmitting ? <ArrowPathIcon className="w-4 h-4 animate-spin" /> : null}
                                    {modalMode === 'create' ? 'Create User' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Layout>
    );
}
