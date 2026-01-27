import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
    XMarkIcon,
    CheckCircleIcon,
    CubeIcon,
    DocumentTextIcon,
    RocketLaunchIcon,
    CodeBracketIcon,
} from '@heroicons/react/24/outline';

interface Step {
    id: string;
    title: string;
    description: string;
    icon: React.ElementType;
    href: string;
    completed: boolean;
}

export function GettingStartedGuide() {
    const [isVisible, setIsVisible] = useState(true);
    const [steps, setSteps] = useState<Step[]>([
        {
            id: 'create-model',
            title: 'Create your first model',
            description: 'Define your data schema using the visual Model Creator.',
            icon: CubeIcon,
            href: '/models/create',
            completed: false,
        },
        {
            id: 'explore-database',
            title: 'Explore the database',
            description: 'View your tables and data in the Database Explorer.',
            icon: DocumentTextIcon,
            href: '/database',
            completed: false,
        },
        {
            id: 'check-api-docs',
            title: 'Check API documentation',
            description: 'Learn how to integrate with your auto-generated APIs.',
            icon: CodeBracketIcon,
            href: '/api-docs',
            completed: false,
        },
        {
            id: 'deploy',
            title: 'Deploy your backend',
            description: 'Take your project live with one-click deployment.',
            icon: RocketLaunchIcon,
            href: '/settings',
            completed: false,
        },
    ]);

    useEffect(() => {
        const dismissed = localStorage.getItem('digibase_getting_started_dismissed');
        if (dismissed === 'true') {
            setIsVisible(false);
        }

        const completedSteps = JSON.parse(localStorage.getItem('digibase_onboarding_steps') || '[]');
        if (completedSteps.length > 0) {
            setSteps(prev => prev.map(step => ({
                ...step,
                completed: completedSteps.includes(step.id)
            })));
        }
    }, []);

    const handleDismiss = () => {
        setIsVisible(false);
        localStorage.setItem('digibase_getting_started_dismissed', 'true');
    };

    const handleStepClick = (stepId: string) => {
        const completedSteps = JSON.parse(localStorage.getItem('digibase_onboarding_steps') || '[]');
        if (!completedSteps.includes(stepId)) {
            completedSteps.push(stepId);
            localStorage.setItem('digibase_onboarding_steps', JSON.stringify(completedSteps));
        }
        setSteps(prev => prev.map(step =>
            step.id === stepId ? { ...step, completed: true } : step
        ));
    };

    if (!isVisible) return null;

    const completedCount = steps.filter(s => s.completed).length;
    const progress = (completedCount / steps.length) * 100;

    return (
        <div className="bg-gradient-to-br from-[#2a2a2a] to-[#1e1e1e] border border-[#3a3a3a] rounded-xl overflow-hidden animate-slideUp mb-8">
            <div className="px-6 py-4 border-b border-[#3a3a3a] flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] flex items-center justify-center">
                        <RocketLaunchIcon className="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h3 className="text-white font-semibold">Getting Started</h3>
                        <p className="text-xs text-[#a1a1a1]">{completedCount}/{steps.length} steps completed</p>
                    </div>
                </div>
                <button
                    onClick={handleDismiss}
                    className="p-1.5 text-[#6b6b6b] hover:text-white hover:bg-[#3a3a3a] rounded-lg transition-colors"
                >
                    <XMarkIcon className="w-5 h-5" />
                </button>
            </div>

            {/* Progress Bar */}
            <div className="h-1 bg-[#1c1c1c]">
                <div
                    className="h-full bg-gradient-to-r from-[#3ecf8e] to-[#24b47e] transition-all duration-500"
                    style={{ width: `${progress}%` }}
                />
            </div>

            <div className="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {steps.map((step) => (
                    <Link
                        key={step.id}
                        to={step.href}
                        onClick={() => handleStepClick(step.id)}
                        className={`group relative p-4 rounded-lg border transition-all duration-200 ${step.completed
                                ? 'bg-[#3ecf8e]/10 border-[#3ecf8e]/30'
                                : 'bg-[#232323] border-[#3a3a3a] hover:border-[#3ecf8e]/50'
                            }`}
                    >
                        <div className="flex items-start gap-3">
                            <div className={`p-2 rounded-lg ${step.completed
                                    ? 'bg-[#3ecf8e]/20 text-[#3ecf8e]'
                                    : 'bg-[#2a2a2a] text-[#6b6b6b] group-hover:text-[#3ecf8e]'
                                }`}>
                                {step.completed ? (
                                    <CheckCircleIcon className="w-5 h-5" />
                                ) : (
                                    <step.icon className="w-5 h-5" />
                                )}
                            </div>
                            <div className="flex-1 min-w-0">
                                <h4 className={`text-sm font-medium truncate ${step.completed ? 'text-[#3ecf8e]' : 'text-white'
                                    }`}>
                                    {step.title}
                                </h4>
                                <p className="text-xs text-[#6b6b6b] mt-0.5 line-clamp-2">
                                    {step.description}
                                </p>
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
