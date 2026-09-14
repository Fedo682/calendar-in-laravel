/**
 * Props the Profile pages receive that nothing else shares.
 *
 * These live here rather than in `@/types` because only this page tree uses
 * them; `@/types/shared.ts` mirrors the Inertia middleware and nothing else.
 */

export interface TimezoneOption {
    /** IANA identifier, e.g. `Europe/Berlin`. */
    value: string;
    /** Region stripped and underscores expanded, e.g. `Berlin`. */
    label: string;
}

/** One `<optgroup>` worth of zones, as built by `ProfileController::edit()`. */
export interface TimezoneGroup {
    /** `Europe`, `America`, ... or `Other` for region-less ids such as `UTC`. */
    region: string;
    timezones: TimezoneOption[];
}
