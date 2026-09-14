<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $timezone IANA identifier.
 * @property string $theme system|light|dark.
 * @property int $week_starts_on 0=Sunday.
 * @property string $time_format 12h|24h.
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GroupUser $pivot Only set when loaded via Group::members()/User::memberGroups().
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'theme', 'week_starts_on', 'time_format'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use \Illuminate\Auth\MustVerifyEmail;

    /** Per-instance memo for isSuperAdmin(); not a database column. */
    private ?bool $isSuperAdmin = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Without this the column comes back as a string on SQLite, which
            // breaks strict comparisons against the 0-6 day index.
            'week_starts_on' => 'integer',
        ];
    }

    /**
     * The zone this user's times should be rendered in.
     *
     * Everything is stored in UTC; this is the only place a display zone is
     * resolved, so callers never have to decide what to do about a blank or
     * unrecognised identifier.
     */
    public function viewerZone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->timezone);
        } catch (\Exception) {
            // Validation keeps this from happening, but a row edited outside
            // the app must not take a page down.
            return new \DateTimeZone('UTC');
        }
    }

    /**
     * Groups this user created (Super Admin only, in practice).
     *
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'created_by');
    }

    /**
     * Groups this user belongs to as a member (Admin or Member role).
     *
     * @return BelongsToMany<Group, $this, GroupUser>
     */
    public function memberGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->using(GroupUser::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Platform-wide roles (currently only 'super_admin' is ever assigned here).
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * Memoised because Gate::before calls it on every single authorization
     * check, and it was issuing one query each time.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin ??= $this->roles()
            ->where('name', RoleName::SuperAdmin->value)
            ->exists();
    }

    public function roleInGroup(Group $group): ?string
    {
        return $group->roleFor($this);
    }

    public function roleEnumInGroup(Group $group): ?RoleName
    {
        return RoleName::tryFromName($this->roleInGroup($group));
    }

    /**
     * The calendar this user owns outright, created on first use.
     *
     * Every user is entitled to exactly one; the unique index on
     * calendars.owner_id is what guarantees it, and firstOrCreate keeps a
     * race from turning into a constraint violation the caller has to handle.
     */
    public function personalCalendar(): Calendar
    {
        return Calendar::firstOrCreate(
            ['owner_id' => $this->id],
            [
                'type' => Calendar::TYPE_PERSONAL,
                'group_id' => null,
                'name' => 'Personal',
                'description' => 'Your private calendar. Only you can see what is on it.',
                'color' => '#6366f1',
                'created_by' => $this->id,
            ],
        );
    }

    /**
     * IDs of every calendar this user can see: all of them for a Super
     * Admin, otherwise only those belonging to groups they're a member of.
     *
     * @return Collection<int, int>
     */
    public function accessibleCalendarIds(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Calendar::query()->pluck('id');
        }

        // Personal calendars have no group, so membership alone would miss
        // them. A Super Admin already sees every calendar above, including
        // other people's personal ones - EventRedactor is what keeps the
        // contents of those from being readable.
        return Calendar::query()
            ->where('owner_id', $this->id)
            ->orWhereIn('group_id', $this->memberGroups()->select('groups.id'))
            ->pluck('id');
    }
}
