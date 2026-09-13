<?php

namespace App\Support\Calendar;

/**
 * Marks a region of code as "this write originated from a remote sync, do
 * not echo it back out".
 *
 * When the Google puller applies a remote change locally, the model observer
 * would otherwise see an ordinary save and queue a push back to Google,
 * producing a loop. Wrapping the write suppresses that.
 *
 * This is only one half of the loop prevention. It is process-local, so it
 * cannot stop the webhook Google fires about our own write from arriving in
 * a different process later; content hashing on the event link covers that
 * case. Both mechanisms are required - neither is sufficient alone.
 */
final class SyncContext
{
    private static int $depth = 0;

    /**
     * Run the callback with side effects suppressed, restoring the previous
     * state afterwards even if the callback throws.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutSideEffects(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    public static function suppressed(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Reset the nesting counter. Intended for test teardown only - a leaked
     * depth would silently disable outbound sync for the rest of a process.
     */
    public static function reset(): void
    {
        self::$depth = 0;
    }
}
