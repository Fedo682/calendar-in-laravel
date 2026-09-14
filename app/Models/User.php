<?php

namespace App\Models;

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

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('name', 'super_admin')->exists();
    }

    public function roleInGroup(Group $group): ?string
    {
        return $group->roleFor($this);
    }

    /**
     * IDs of every calendar this user can see: all of them for a Super
     * Admin, otherwise only those belonging to groups they're a member of.
     *
     * @return Collection<int, int>
     */
    public function accessibleCalendarIds(): Collection
    {
        return $this->isSuperAdmin()
            ? Calendar::query()->pluck('id')
            : Calendar::whereIn('group_id', $this->memberGroups()->pluck('groups.id'))->pluck('id');
    }
}
