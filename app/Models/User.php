<?php

namespace App\Models;

use App\Billing\Plan;
use App\Billing\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
     * The Google and GitHub identities linked to this account.
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Whether this user may open the admin panel.
     *
     * Membership is an email allowlist rather than a role, so it is compared
     * case-insensitively against the configured addresses.
     */
    public function isAdmin(): bool
    {
        $admins = config('admin.emails', []);

        return $this->email !== null
            && $this->email !== ''
            && in_array(strtolower($this->email), $admins, true);
    }

    /**
     * The subscription this user is on.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Every payment this user has made.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * This user's staff role.
     *
     * An unknown or missing value falls back to User rather than throwing, so a
     * hand-edited database cannot lock somebody out of their own account.
     */
    public function role(): Role
    {
        return Role::tryFrom((string) ($this->attributes['role'] ?? '')) ?? Role::User;
    }

    /**
     * The tier this user is entitled to.
     *
     * A live subscription is authoritative; otherwise the column is used so a
     * manually granted or comped tier still works.
     */
    public function plan(): Plan
    {
        $subscription = $this->relationLoaded('subscription')
            ? $this->getRelation('subscription')
            : $this->subscription()->first();

        if ($subscription !== null && $subscription->status === 'active') {
            $fromSubscription = Plan::tryFrom((string) $subscription->plan);

            if ($fromSubscription !== null) {
                return $fromSubscription;
            }
        }

        return Plan::tryFrom((string) ($this->attributes['plan'] ?? '')) ?? Plan::Free;
    }

    /**
     * Whether this user's tier includes a feature.
     *
     * Named canUse rather than can, which is Laravel's authorization helper and
     * already has a meaning on this class.
     */
    public function canUse(string $feature): bool
    {
        return $this->plan()->allows($feature);
    }

    /**
     * Whether this user holds a role, or a stronger one.
     */
    public function hasRole(Role $role): bool
    {
        return $this->role()->includes($role);
    }

    /**
     * Move this user onto a tier and keep the subscription row in step.
     */
    public function subscribeTo(Plan $plan, ?string $provider = null, ?string $providerId = null): Subscription
    {
        $subscription = Subscription::query()->updateOrCreate(
            ['user_id' => $this->getKey()],
            [
                'plan' => $plan->value,
                'status' => 'active',
                'provider' => $provider ?? 'none',
                'provider_id' => $providerId,
                'renews_at' => $plan === Plan::Free ? null : now()->addMonth(),
                'cancelled_at' => null,
            ],
        );

        // Cached so the middleware and the UI agree for the rest of the request.
        $this->setRelation('subscription', $subscription);
        $this->forceFill(['plan' => $plan->value])->save();

        return $subscription;
    }
}
