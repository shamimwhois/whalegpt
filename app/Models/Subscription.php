<?php

namespace App\Models;

use App\Billing\Plan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's current billing state.
 *
 * One row per user, enforced by a unique index: a checkout that is delivered
 * twice upgrades the same row rather than granting the tier twice over.
 */
class Subscription extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'provider',
        'provider_id',
        'renews_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'renews_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The tier this subscription grants, when it is a known one.
     */
    public function tier(): ?Plan
    {
        return Plan::tryFrom((string) $this->plan);
    }

    /**
     * Whether this subscription is currently granting access.
     *
     * A cancelled subscription that has run out no longer grants anything, even
     * though its row is still there for the payment history.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->renews_at === null || $this->renews_at->isFuture();
    }
}
