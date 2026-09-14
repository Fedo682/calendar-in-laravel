import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment } from 'react';

export interface Crumb {
    label: string;
    /** Omitted on the final crumb, which is the current page. */
    href?: string;
}

export interface BreadcrumbsProps {
    items: Crumb[];
    className?: string;
}

/**
 * Group > Calendar > Event.
 *
 * The last crumb is rendered as text with `aria-current="page"` even if the
 * caller supplied an href - linking to the page you are already on is noise
 * for a screen-reader user and a dead click for everyone else.
 */
export default function Breadcrumbs({ items, className }: BreadcrumbsProps) {
    return (
        <nav aria-label="Breadcrumb" className={className}>
            <ol className="text-footnote text-content-secondary flex flex-wrap items-center gap-1">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <Fragment key={`${item.label}-${String(index)}`}>
                            <li className="inline-flex items-center">
                                {isLast || item.href === undefined ? (
                                    <span
                                        aria-current={
                                            isLast ? 'page' : undefined
                                        }
                                        className={cn(
                                            isLast && 'text-content font-medium',
                                        )}
                                    >
                                        {item.label}
                                    </span>
                                ) : (
                                    <Link
                                        href={item.href}
                                        className="hover:text-content ease-hig duration-fast rounded-control transition"
                                    >
                                        {item.label}
                                    </Link>
                                )}
                            </li>

                            {!isLast && (
                                <li aria-hidden="true" className="inline-flex">
                                    <ChevronRight className="text-content-tertiary size-3.5" />
                                </li>
                            )}
                        </Fragment>
                    );
                })}
            </ol>
        </nav>
    );
}
