import { useState, useEffect } from 'react';
import { echo } from '../lib/echo';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    BoltIcon,
    UserCircleIcon
} from '@heroicons/react/24/outline';

interface Activity {
    id: string;
    type: 'created' | 'updated' | 'deleted';
    modelName: string;
    data: any;
    user: any;
    timestamp: string;
}

export const ActivityFeed = () => {
    const [activities, setActivities] = useState<Activity[]>([]);

    useEffect(() => {
        const channel = echo.channel('digibase.activity');

        channel.listen('.ModelActivity', (e: any) => {
            console.log('Real-time event received:', e);
            const newActivity: Activity = {
                id: Math.random().toString(36).substr(2, 9),
                type: e.type,
                modelName: e.modelName,
                data: e.data,
                user: e.user,
                timestamp: new Date().toLocaleTimeString(),
            };
            setActivities(prev => [newActivity, ...prev].slice(0, 5));
        });

        return () => {
            echo.leaveChannel('digibase.activity');
        };
    }, []);

    return (
        <div className="bg-slate-900/50 border border-slate-800 rounded-xl overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 className="text-white font-semibold flex items-center gap-2">
                    <BoltIcon className="w-5 h-5 text-yellow-500" />
                    Live System Activity
                </h3>
                <span className="flex h-2 w-2 relative">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                </span>
            </div>
            <div className="divide-y divide-slate-800">
                {activities.length === 0 ? (
                    <div className="px-6 py-8 text-center text-slate-500 text-sm">
                        Waiting for activity...
                    </div>
                ) : (
                    activities.map((activity) => (
                        <div key={activity.id} className="px-6 py-4 flex gap-4 hover:bg-slate-800/30 transition-colors">
                            <div className={`p-2 rounded-lg h-fit ${activity.type === 'created' ? 'bg-green-500/10 text-green-500' :
                                    activity.type === 'updated' ? 'bg-blue-500/10 text-blue-500' :
                                        'bg-red-500/10 text-red-500'
                                }`}>
                                {activity.type === 'created' && <PlusIcon className="w-5 h-5" />}
                                {activity.type === 'updated' && <PencilIcon className="w-5 h-5" />}
                                {activity.type === 'deleted' && <TrashIcon className="w-5 h-5" />}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-white text-sm font-medium truncate">
                                        {activity.modelName} {activity.type}
                                    </p>
                                    <span className="text-slate-500 text-[10px] uppercase font-bold">{activity.timestamp}</span>
                                </div>
                                <div className="mt-1 flex items-center gap-2">
                                    <UserCircleIcon className="w-3.5 h-3.5 text-slate-400" />
                                    <span className="text-slate-400 text-xs">System User</span>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
};
