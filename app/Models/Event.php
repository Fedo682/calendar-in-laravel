<?php

namespace App\Models;

use App\Enums\EventVisibility;
use App\Observers\EventObserver;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $calendar_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $all_day
 * @property EventVisibility $visibility
 * @property string|null $recurrence_rule
 * @property string|null $recurrence_timezone
 * @property list<string>|null $recurrence_exdates
 * @property list<string>|null $recurrence_rdates
 * @property Carbon|null $recurrence_until
 * @property int|null $recurrence_parent_id
 * @property Carbon|null $recurrence_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Calendar $calendar
 */
#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'all_day',
        'visibility',
        'recurrence_rule',
        'recurrence_timezone',
        'recurrence_exdates',
        'recurrence_rdates',
        'recurrence_until',
        'recurrence_parent_id',
        'recurrence_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            // The DB column is a plain string; this cast is what actually
            // constrains it to the three known cases.
            'visibility' => EventVisibility::class,
            // Arrays of UTC ISO-8601 instants. JSON rather than a side table
            // because they are only ever read and written whole, alongside
            // the row that owns them.
            'recurrence_exdates' => 'array',
            'recurrence_rdates' => 'array',
            // Denormalised by EventObserver, never set by a caller.
            'recurrence_until' => 'datetime',
            // On an override row, the original start of the instance it
            // replaces - iCalendar's RECURRENCE-ID.
            'recurrence_id' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The series this row is an override of, if it is one.
     *
     * @return BelongsTo<Event, $this>
     */
    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'recurrence_parent_id');
    }

    /**
     * The "this event only" edits made to instances of this series.
     *
     * @return HasMany<Event, $this>
     */
    public function recurrenceOverrides(): HasMany
    {
        return $this->hasMany(Event::class, 'recurrence_parent_id');
    }

    /**
     * A master carrying a rule. An override is not recurring - it stands in
     * for exactly one instance and carries no rule of its own.
     */
    public function isRecurring(): bool
    {
        return $this->recurrence_rule !== null && $this->recurrence_rule !== '';
    }
}
