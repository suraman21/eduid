import React from 'react';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';
type Size = 'sm' | 'md';

interface BaseProps {
    variant?: Variant;
    size?: Size;
    icon?: LucideIcon;
    iconRight?: LucideIcon;
    loading?: boolean;
    children?: React.ReactNode;
    className?: string;
}

const variants: Record<Variant, string> = {
    primary:   'bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20',
    secondary: 'bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10',
    danger:    'bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-500/20',
    ghost:     'text-slate-400 hover:text-slate-200 hover:bg-white/5',
};

const sizes: Record<Size, string> = {
    sm: 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
    md: 'px-4 py-2.5 text-sm rounded-xl gap-2',
};

function inner(icon: LucideIcon | undefined, iconRight: LucideIcon | undefined, loading: boolean | undefined, children: React.ReactNode, size: Size) {
    const Icon = icon;
    const IconRight = iconRight;
    const iconSize = size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4';
    return (
        <>
            {loading ? (
                <span className={`${iconSize} border-2 border-current border-t-transparent rounded-full animate-spin`} />
            ) : Icon ? <Icon className={iconSize} strokeWidth={2} /> : null}
            {children}
            {IconRight && !loading ? <IconRight className={iconSize} strokeWidth={2} /> : null}
        </>
    );
}

const baseClass = 'inline-flex items-center justify-center font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed';

type ButtonProps = BaseProps & React.ButtonHTMLAttributes<HTMLButtonElement>;

export default function Button({
    variant = 'primary', size = 'md', icon, iconRight, loading, children, className = '', disabled, ...rest
}: ButtonProps) {
    return (
        <button
            className={`${baseClass} ${variants[variant]} ${sizes[size]} ${className}`}
            disabled={disabled || loading}
            {...rest}
        >
            {inner(icon, iconRight, loading, children, size)}
        </button>
    );
}

interface LinkButtonProps extends BaseProps {
    href: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    as?: 'a' | 'button';
}

export function LinkButton({
    variant = 'primary', size = 'md', icon, iconRight, children, className = '', href, method, as,
}: LinkButtonProps) {
    return (
        <Link
            href={href}
            method={method}
            as={as}
            className={`${baseClass} ${variants[variant]} ${sizes[size]} ${className}`}
        >
            {inner(icon, iconRight, false, children, size)}
        </Link>
    );
}
