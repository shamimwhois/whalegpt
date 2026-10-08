<?php

namespace App\Billing;

/**
 * The subscription tier a user is on.
 *
 * A tier is a billing fact, not a permission: it answers "what has this person
 * paid for". Staff roles (App\Billing\Role) answer "what may this person do",
 * and the two are independent — staff are not billed and paying users are not
 * staff.
 *
 * The order of the cases is the order of the tiers, so comparing them with
 * spaceship operators gives "is at least this good".
 */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Premium = 'premium';

    /**
     * The human name used on the pricing page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
            self::Premium => 'Premium',
        };
    }

    /**
     * The monthly price in cents, so no float ever touches money.
     */
    public function priceInCents(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Pro => 1900,
            self::Premium => 4900,
        };
    }

    /**
     * What this tier allows, in one place.
     *
     * Feature keys are plain strings so adding one does not mean editing every
     * gate; see EnsurePlan for where they are checked.
     */
    public function features(): array
    {
        return match ($this) {
            self::Free => ['chat', 'image', 'ocr'],
            self::Pro => ['chat', 'image', 'ocr', 'audio', 'transcription', 'models', 'workspace', 'agents'],
            self::Premium => [
                'chat', 'image', 'ocr', 'audio', 'transcription', 'camera',
                'models', 'workspace', 'agents', 'local_models', 'priority',
            ],
        };
    }

    /**
     * Whether this tier includes a feature.
     */
    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    /**
     * The next tier up, or null when this is the top one.
     */
    public function upgraded(): ?self
    {
        return match ($this) {
            self::Free => self::Pro,
            self::Pro => self::Premium,
            self::Premium => null,
        };
    }

    /**
     * Format the price for display.
     */
    public function price(): string
    {
        return $this->priceInCents() === 0
            ? 'Free'
            : '$'.number_format($this->priceInCents() / 100, 2).'/mo';
    }
}
