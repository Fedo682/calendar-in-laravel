<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * Memoised roleFor() results, keyed by user id. Not a database column.
     *
     * @var array<int, string|null>
     */
    private array $roleMemo = [];

    protected $fillable = ['name', 'description', 'created_by'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this, GroupUser>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->using(GroupUser::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Calendar, $this>
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * This user's role in this group, or null if they are not a member.
     *
     * Memoised per instance and per user: redacting a page of events asks the
     * same question once per event, and before this it was one query each.
     */
    public function roleFor(User $user): ?string
    {
        if (array_key_exists($user->id, $this->roleMemo)) {
            return $this->roleMemo[$user->id];
        }

        return $this->roleMemo[$user->id] = GroupUser::where('group_id', $this->id)
            ->where('user_id', $user->id)
            ->with('role')
            ->first()
            ?->role
            ?->name;
    }

    /**
     * Drop the memoised roles. Needed after a membership changes on an
     * instance that is still in scope.
     */
    public function forgetRoleMemo(): void
    {
        $this->roleMemo = [];
    }
}
