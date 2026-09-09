<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    /**
     * Groups this user created (Super Admin only, in practice).
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'created_by');
    }

    /**
     * Groups this user belongs to as a member (Admin or Member role).
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
}
