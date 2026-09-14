import { useCallback, useSyncExternalStore } from 'react';

/**
 * Which calendars the viewer has switched off in the source list.
 *
 * Stored as the *hidden* set rather than the visible one, so a calendar the
 * user gains access to later shows up by default instead of silently staying
 * off. Per-device and per-browser on purpose: this is a viewing preference,
 * not an account setting, and it changes often enough that round-tripping it
 * to the server on every checkbox would be a request per click.
 *
 * A module-level store rather than a context: the source list lives in the
 * shell and the consumers are calendar views several levels down inside the
 * page, and a provider spanning both would have to wrap the persistent layout
 * *and* every page that renders its own chrome.
 */
const STORAGE_KEY = 'calendar:hidden';

let hidden: ReadonlySet<number> = new Set();
let loaded = false;

const listeners = new Set<() => void>();

function read(): ReadonlySet<number> {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (raw === null) {
            return new Set();
        }

        const parsed: unknown = JSON.parse(raw);

        return new Set(
            Array.isArray(parsed)
                ? parsed.filter((id): id is number => typeof id === 'number')
                : [],
        );
    } catch {
        // Unreadable storage, or something else wrote garbage under the key.
        return new Set();
    }
}

function write(next: ReadonlySet<number>): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...next]));
    } catch {
        // Nothing to do - the in-memory set still drives this session.
    }
}

function emit(): void {
    for (const listener of listeners) {
        listener();
    }
}

function subscribe(listener: () => void): () => void {
    // Hydrated lazily: reading localStorage at module scope would run during
    // the server bundle's import, where there is no localStorage at all.
    if (!loaded) {
        loaded = true;
        hidden = read();
    }

    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function getSnapshot(): ReadonlySet<number> {
    return hidden;
}

const EMPTY: ReadonlySet<number> = new Set();

function getServerSnapshot(): ReadonlySet<number> {
    return EMPTY;
}

export function setCalendarHidden(id: number, isHidden: boolean): void {
    const next = new Set(hidden);

    if (isHidden) {
        next.add(id);
    } else {
        next.delete(id);
    }

    hidden = next;
    write(next);
    emit();
}

export interface CalendarVisibility {
    hidden: ReadonlySet<number>;
    isVisible: (id: number) => boolean;
    toggle: (id: number) => void;
    setHidden: (id: number, isHidden: boolean) => void;
}

export function useCalendarVisibility(): CalendarVisibility {
    const current = useSyncExternalStore(
        subscribe,
        getSnapshot,
        getServerSnapshot,
    );

    const isVisible = useCallback((id: number) => !current.has(id), [current]);

    const toggle = useCallback(
        (id: number) => {
            setCalendarHidden(id, !current.has(id));
        },
        [current],
    );

    return {
        hidden: current,
        isVisible,
        toggle,
        setHidden: setCalendarHidden,
    };
}

export default useCalendarVisibility;
