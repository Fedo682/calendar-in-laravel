import { cn } from '@/lib/utils';
import type { LabelHTMLAttributes, ReactNode } from 'react';

interface InputLabelProps extends LabelHTMLAttributes<HTMLLabelElement> {
    value?: string;
    children?: ReactNode;
}

export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}: InputLabelProps) {
    return (
        <label
            {...props}
            className={cn('block text-sm font-medium text-gray-700', className)}
        >
            {value ? value : children}
        </label>
    );
}
