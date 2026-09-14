import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import Spinner from './Spinner';

export type ButtonVariant =
    | 'primary'
    | 'secondary'
    | 'ghost'
    | 'destructive'
    | 'glass';

export type ButtonSize = 'sm' | 'md' | 'lg' | 'icon';

/**
 * `material-ultrathin` is the thinnest material on purpose: a button is a
 * small target that usually sits *on* a card, and stacking a thicker blur on
 * an already-blurred surface reads as mud.
 */
const VARIANTS: Record<ButtonVariant, string> = {
    primary:
        'bg-accent text-on-accent shadow-raised hover:bg-accent-hover active:scale-[0.98]',
    secondary:
        'bg-surface-raised text-content border border-hairline hover:bg-accent-soft hover:border-accent/40 active:scale-[0.98]',
    ghost: 'text-content-secondary hover:bg-surface-raised hover:text-content',
    destructive:
        'bg-danger text-on-accent shadow-raised hover:brightness-110 active:scale-[0.98]',
    glass: 'material-ultrathin text-content hover:glow active:scale-[0.98]',
};

const SIZES: Record<ButtonSize, string> = {
    sm: 'h-8 gap-1.5 rounded-control px-3 text-footnote',
    md: 'h-10 gap-2 rounded-control px-4 text-subhead',
    lg: 'h-12 gap-2 rounded-field px-6 text-body',
    icon: 'size-10 rounded-control',
};

const ICON_SIZES: Record<ButtonSize, string> = {
    sm: 'size-3.5',
    md: 'size-4',
    lg: 'size-[1.125rem]',
    icon: 'size-[1.125rem]',
};

export interface ButtonStyleOptions {
    variant?: ButtonVariant;
    size?: ButtonSize;
    fullWidth?: boolean;
    className?: string;
}

/**
 * Exported so anything that has to be an `<a>`, an Inertia `<Link>` or a
 * Headless UI slot can still look like a button without re-deriving the
 * class string.
 */
export function buttonStyles({
    variant = 'secondary',
    size = 'md',
    fullWidth = false,
    className,
}: ButtonStyleOptions = {}): string {
    return cn(
        'ease-hig duration-fast relative inline-flex items-center justify-center font-medium whitespace-nowrap transition select-none',
        'disabled:pointer-events-none disabled:opacity-40',
        VARIANTS[variant],
        SIZES[size],
        fullWidth && 'w-full',
        className,
    );
}

interface CommonButtonProps extends ButtonStyleOptions {
    loading?: boolean;
    icon?: LucideIcon;
    iconPosition?: 'left' | 'right';
    children?: ReactNode;
}

/**
 * The label and icon in one run, with the spinner swapped in for the icon
 * while loading so the button does not change width mid-request.
 */
function ButtonContent({
    loading = false,
    icon: Icon,
    iconPosition = 'left',
    size = 'md',
    children,
}: CommonButtonProps) {
    const iconClass = ICON_SIZES[size];

    const leading =
        loading && iconPosition === 'left' ? (
            <Spinner size="sm" className={iconClass} label={null} />
        ) : Icon && iconPosition === 'left' ? (
            <Icon aria-hidden="true" className={iconClass} />
        ) : null;

    const trailing =
        loading && iconPosition === 'right' ? (
            <Spinner size="sm" className={iconClass} label={null} />
        ) : Icon && iconPosition === 'right' ? (
            <Icon aria-hidden="true" className={iconClass} />
        ) : null;

    // An icon-only button that is loading has nowhere to put the spinner
    // except in place of the icon, which the branches above already do.
    return (
        <>
            {leading}
            {children}
            {trailing}
        </>
    );
}

export interface ButtonProps
    extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'>,
        CommonButtonProps {}

export default function Button({
    variant = 'secondary',
    size = 'md',
    fullWidth,
    loading = false,
    icon,
    iconPosition = 'left',
    className,
    disabled,
    children,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            {...props}
            // Defaulted rather than left to the browser: an un-typed button
            // inside a form submits it, which is almost never what a toolbar
            // or dialog button wants.
            type={type}
            disabled={disabled ?? loading}
            aria-busy={loading || undefined}
            className={buttonStyles({ variant, size, fullWidth, className })}
        >
            <ButtonContent
                loading={loading}
                icon={icon}
                iconPosition={iconPosition}
                size={size}
            >
                {children}
            </ButtonContent>
        </button>
    );
}

export interface LinkButtonProps
    extends Omit<InertiaLinkProps, 'children' | 'className' | 'size'>,
        CommonButtonProps {}

/** An Inertia `<Link>` wearing the button's clothes. */
export function LinkButton({
    variant = 'secondary',
    size = 'md',
    fullWidth,
    loading = false,
    icon,
    iconPosition = 'left',
    className,
    children,
    ...props
}: LinkButtonProps) {
    return (
        <Link
            {...props}
            aria-busy={loading || undefined}
            className={buttonStyles({ variant, size, fullWidth, className })}
        >
            <ButtonContent
                loading={loading}
                icon={icon}
                iconPosition={iconPosition}
                size={size}
            >
                {children}
            </ButtonContent>
        </Link>
    );
}
