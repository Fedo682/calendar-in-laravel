import { cn } from '@/lib/utils';
import { useState } from 'react';

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg';

const SIZES: Record<AvatarSize, string> = {
    xs: 'size-6 text-caption2',
    sm: 'size-8 text-caption1',
    md: 'size-10 text-footnote',
    lg: 'size-16 text-title3',
};

/**
 * A stable hue per name, so the same person is the same colour on every page
 * and in every session. A hash rather than an index, because there is no list
 * to index into - avatars are rendered one at a time from whatever the server
 * happened to send.
 */
function hueFor(seed: string): number {
    let hash = 0;

    for (let i = 0; i < seed.length; i++) {
        // eslint-disable-next-line no-bitwise
        hash = (hash << 5) - hash + seed.charCodeAt(i);
        // eslint-disable-next-line no-bitwise
        hash |= 0;
    }

    return Math.abs(hash) % 360;
}

/** "Ada Lovelace" -> "AL", "Ada" -> "A". Never more than two letters. */
function initialsFor(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    const first = words[0]?.[0] ?? '';
    const last = words.length > 1 ? (words[words.length - 1]?.[0] ?? '') : '';

    return (first + last).toUpperCase();
}

export interface AvatarProps {
    name: string;
    src?: string | null;
    size?: AvatarSize;
    className?: string;
}

export default function Avatar({
    name,
    src,
    size = 'md',
    className,
}: AvatarProps) {
    const [failed, setFailed] = useState(false);
    const hue = hueFor(name);

    const showImage = src != null && src !== '' && !failed;

    return (
        <span
            className={cn(
                'border-hairline relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border font-semibold select-none',
                SIZES[size],
                className,
            )}
            style={
                showImage
                    ? undefined
                    : {
                          // oklch keeps every hue at the same perceived
                          // lightness, so no initial is harder to read than
                          // any other - which is exactly the failure mode of
                          // hashing straight into hsl().
                          backgroundColor: `oklch(0.62 0.13 ${hue})`,
                          color: 'oklch(0.99 0 0)',
                      }
            }
            title={name}
        >
            {showImage ? (
                <img
                    src={src}
                    alt={name}
                    className="size-full object-cover"
                    onError={() => {
                        setFailed(true);
                    }}
                />
            ) : (
                <span aria-hidden="true">{initialsFor(name)}</span>
            )}
            <span className="sr-only">{name}</span>
        </span>
    );
}
