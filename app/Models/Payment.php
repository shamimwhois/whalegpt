<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to take money, successful or not.
 *
 * Kept even when a charge fails, so a user who was declined can see why rather
 * than being told nothing happened.
 */
class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'provider',
        'provider_id',
        'amount_in_cents',
        'currency',
        'status',
        'description',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Whether the money actually moved.
     */
    public function succeeded(): bool
    {
        return $this->status === 'paid';
    }
}
