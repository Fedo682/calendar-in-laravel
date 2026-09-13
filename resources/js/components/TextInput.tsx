import { cn } from '@/lib/utils';
import type { InputHTMLAttributes, Ref } from 'react';
import { useEffect, useImperativeHandle, useRef } from 'react';

export interface TextInputHandle {
    focus: () => void;
}

interface TextInputProps extends InputHTMLAttributes<HTMLInputElement> {
    isFocused?: boolean;
    /** React 19 passes refs as a normal prop - no forwardRef needed. */
    ref?: Ref<TextInputHandle>;
}

export default function TextInput({
    type = 'text',
    className = '',
    isFocused = false,
    ref,
    ...props
}: TextInputProps) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={cn(
                'rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                className,
            )}
            ref={localRef}
        />
    );
}
