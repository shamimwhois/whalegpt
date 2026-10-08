<?php

namespace App\Billing;

use App\Models\Payment;
use App\Models\User;
use RuntimeException;

/**
 * Takes a subscription payment through a provider.
 *
 * A provider is behind an interface rather than called inline so the checkout
 * route does not care which one is configured, and so a deployment with no
 * payment keys degrades to a clear "billing is not configured" message instead
 * of a fatal error on a missing SDK.
 */
interface PaymentGateway
{
    /**
     * The name recorded against payments and subscriptions.
     */
    public function name(): string;

    /**
     * Whether this gateway is configured and can be used.
     */
    public function available(): bool;

    /**
     * Start a checkout and return where to send the browser.
     *
     * @throws RuntimeException when the gateway is unavailable.
     */
    public function checkout(User $user, Plan $plan): string;

    /**
     * Open the provider's customer portal for managing an existing subscription.
     *
     * @throws RuntimeException when the gateway is unavailable.
     */
    public function portal(User $user): string;

    /**
     * Record a payment and apply the tier it bought.
     *
     * @param  array<string, mixed>  $payload  The provider's event body.
     */
    public function handleWebhook(array $payload): ?Payment;
}
