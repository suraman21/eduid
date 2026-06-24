import { motion } from 'framer-motion';

interface Tab { key: string; label: string; }

interface Props {
    tabs: Tab[];
    active: string;
    onChange: (key: string) => void;
}

export default function Tabs({ tabs, active, onChange }: Props) {
    return (
        <div className="flex gap-1 border-b border-slate-200 dark:border-white/5 mb-6">
            {tabs.map(tab => {
                const isActive = tab.key === active;
                return (
                    <button
                        key={tab.key}
                        onClick={() => onChange(tab.key)}
                        className={`relative px-4 py-2.5 text-sm font-medium transition-colors ${
                            isActive
                                ? 'text-purple-600 dark:text-purple-400'
                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                        }`}
                    >
                        {tab.label}
                        {isActive && (
                            <motion.div
                                layoutId="tab-underline"
                                className="absolute bottom-0 left-0 right-0 h-0.5 bg-purple-500"
                            />
                        )}
                    </button>
                );
            })}
        </div>
    );
}
