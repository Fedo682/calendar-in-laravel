<?php

namespace App\Models;

use App\Enums\EventVisibility;
use Database\Factories\CalendarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A calendar is either owned by a group or by a single user.
 *
 * Group calendars carry a group_id and no owner_id; personal calendars carry
 * an owner_id and no group_id. Exactly one of the two is set, which is why
 * both columns are nullable and why $group is nullable on this model.
 *
 * @property int $id
 * @property string $type group|personal
 * @property int|null $group_id Null for personal calendars.
 * @property int|null $owner_id Null for group calendars.
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group|null $group Null for personal calendars.
 * @property-read User|null $owner Null for group calendars.
 */
class Calendar extends Model
{
    /** @use HasFactory<CalendarFactory> */
    use HasFactory;

    public const TYPE_GROUP = 'group';

    public const TYPE_PERSONAL = 'personal';

    protected $fillable = ['type', 'group_id', 'owner_id', 'name', 'description', 'color', 'created_by'];

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The single user a personal calendar belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    /**
     * What a new event on this calendar should default to.
     *
     * Anything on a personal calendar is private unless its owner says
     * otherwise; anything on a shared group calendar is visible to the group,
     * because that is the point of putting it there.
     *
     * Applied by the store request rather than by an observer, so the default
     * is visible at the point a caller could have supplied a value, and so an
     * import or a sync can still write an explicit visibility without a
     * hidden rule overriding it.
     */
    public function defaultEventVisibility(): EventVisibility
    {
        return $this->isPersonal()
            ? EventVisibility::Private
            : EventVisibility::Public;
    }

    /**
     * Whether this user may see the details of an event on this calendar that
     * is not their own - true only for the owner of a personal calendar.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id !== null && $this->owner_id === $user->id;
    }
}
