import { cn } from '@/lib/utils';
import type { HTMLAttributes, ReactNode } from 'react';

/**
 * Which of the four materials the card is made of.
 *
 * `flat` is not a fifth material - it is the escape hatch for cards that
 * repeat (a list of 40 rows, a grid of cells), where per-element
 * backdrop-filter is the difference between a smooth scroll and a janky one.
 */
export type CardMaterial = 'ultrathin' | 'thin' | 'regular' | 'thick' | 'flat';

const MATERIALS: Record<CardMaterial, string> = {
    ultrathin: 'material-ultrathin',
    thin: 'material-thin',
    regular: 'material-regular',
    thick: 'material-thick',
    flat: 'surface-flat',
};

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
    material?: CardMaterial;
    /** Internal padding. `false` for cards that own their own layout. */
    padded?: boolean;
    /** Lifts and brightens on hover; use only when the whole card is a target. */
    interactive?: boolean;
    /** Accent bloom on hover. Implies nothing on its own - pair with interactive. */
    glow?: boolean;
    children?: ReactNode;
}

export default function Card({
    material = 'thin',
    padded = true,
    interactive = false,
    glow = false,
    className,
    children,
    ...props
}: CardProps) {
    return (
        <div
            {...props}
            className={cn(
                'rounded-card ease-hig duration-base transition',
                MATERIALS[material],
                padded && 'p-5',
                interactive &&
                    'hover:border-accent/40 hover:bg-surface-raised cursor-pointer hover:-translate-y-0.5',
                glow && 'hover:glow',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function CardHeader({
    className,
    children,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            {...props}
            className={cn('flex items-start justify-between gap-4', className)}
        >
            {children}
        </div>
    );
}

export function CardTitle({
    className,
    children,
    ...props
}: HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3
            {...props}
            className={cn('text-headline text-content', className)}
        >
            {children}
        </h3>
    );
}

export function CardDescription({
    className,
    children,
    ...props
}: HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p
            {...props}
            className={cn(
                'text-footnote text-content-secondary mt-1',
                className,
            )}
        >
            {children}
        </p>
    );
}
