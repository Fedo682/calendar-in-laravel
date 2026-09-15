<?php

namespace App\Models;

use Database\Factories\CalendarFeedTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string|null $label
 * @property Carbon|null $last_used_at
 * @property string|null $last_ip
 * @property string|null $last_user_agent
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class CalendarFeedToken extends Model
{
    /** @use HasFactory<CalendarFeedTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_hash',
        'label',
        'last_used_at',
        'last_ip',
        'last_user_agent',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
