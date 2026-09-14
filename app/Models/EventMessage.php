<?php

namespace App\Models;

use App\Enums\EventMessageType;
use Database\Factories\EventMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $sender_id
 * @property EventMessageType $type
 * @property Carbon $occurrence_start
 * @property string $body
 * @property array<int, string>|null $conflicting_titles
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read User $sender
 */
class EventMessage extends Model
{
    /** @use HasFactory<EventMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'sender_id',
        'type',
        'occurrence_start',
        'body',
        'conflicting_titles',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventMessageType::class,
            'occurrence_start' => 'datetime',
            'conflicting_titles' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
