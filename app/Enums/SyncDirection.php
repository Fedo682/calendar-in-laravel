<?php

namespace App\Enums;

/**
 * Which way events flow across a link between one of our calendars and a
 * remote (Google) calendar.
 */
enum SyncDirection: string
{
    /** Remote changes come in; nothing of ours goes out. */
    case Pull = 'pull';

    /** Our changes go out; remote changes are ignored. */
    case Push = 'push';

    case Both = 'both';

    public function pulls(): bool
    {
        return $this === self::Pull || $this === self::Both;
    }

    public function pushes(): bool
    {
        return $this === self::Push || $this === self::Both;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pull => 'Import only',
            self::Push => 'Export only',
            self::Both => 'Two-way',
        };
    }
}
