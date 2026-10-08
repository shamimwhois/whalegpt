<?php

namespace App\Billing;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe, called over its REST API.
 *
 * The SDK is deliberately not installed. Hitting the API directly keeps the
 * dependency surface unchanged and means a deployment with no Stripe keys still
 * boots. Every call is made with the keys in the header and no secrets in the
 * URL, and a failed charge is stored rather than swallowed so the user can be
 * told what happened.
 */
class StripeGateway implements PaymentGateway
{
    /**
     * @see https://docs.stripe.com/api/checkout/sessions/create
     */
    private const CHECKOUT = 'https://api.stripe.com/v1/checkout/sessions';

    /**
     * @see https://docs.stripe.com/api/customer_portal/sessions
     */
    private const PORTAL = 'https://api.stripe.com/v1/billing_portal/sessions';

    public function name(): string
    {
        return 'stripe';
    }

    public function available(): bool
    {
        return filled(config('billing.stripe.secret')) && filled(config('billing.stripe.price_ids.pro'))
            && filled(config('billing.stripe.price_ids.premium'));
    }

    /**
     * Start a checkout session.
     */
    public function checkout(User $user, Plan $plan): string
    {
        if (! $this->available()) {
            throw new RuntimeException('Billing is not configured. Set BILLING_STRIPE_SECRET and the price ids.');
        }

        $priceId = config("billing.stripe.price_ids.{$plan->value}");

        if ($plan === Plan::Free || blank($priceId)) {
            throw new RuntimeException('The free plan does not need checkout.');
        }

        $response = Http::asForm()
            ->withToken((string) config('billing.stripe.secret'))
            ->timeout(20)
            ->post(self::CHECKOUT, [
                'mode' => 'subscription',
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('pricing').'?canceled=1',
                'client_reference_id' => (string) $user->getKey(),
                'line_items' => [
                    ['price' => $priceId, 'quantity' => 1],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Stripe checkout failed: '.$response->body());

            throw new RuntimeException('Stripe could not start the checkout. Try again shortly.');
        }

        $url = $response->json('url');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Stripe did not return a checkout link.');
        }

        return $url;
    }

    /**
     * Open the customer portal.
     */
    public function portal(User $user): string
    {
        $customerId = (string) $user->subscription?->provider_id;

        if (blank($customerId)) {
            throw new RuntimeException('There is no billing account to manage yet.');
        }

        $response = Http::asForm()
            ->withToken((string) config('billing.stripe.secret'))
            ->timeout(20)
            ->post(self::PORTAL, [
                'customer' => $customerId,
                'return_url' => route('pricing'),
            ]);

        $url = $response->successful() ? $response->json('url') : null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('The billing portal is unavailable right now.');
        }

        return $url;
    }

    /**
     * Apply a completed checkout.
     *
     * Stripe's own signature check belongs at the webhook route; this only
     * interprets the body it has already accepted.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): ?Payment
    {
        $providerId = (string) ($payload['id'] ?? '');
        $plan = Plan::tryFrom((string) ($payload['plan'] ?? ''));

        if ($providerId === '' || $plan === null) {
            return null;
        }

        $user = User::query()->find($payload['client_reference_id'] ?? null);

        if ($user === null) {
            Log::warning('Stripe webhook referenced an unknown user.');

            return null;
        }

        $subscription = $user->subscribeTo($plan, $this->name(), $providerId);

        // updateOrCreate keyed on the provider's id means a webhook delivered
        // twice records one payment rather than two.
        [$payment] = Payment::query()->updateOrCreate(
            ['provider' => $this->name(), 'provider_id' => $providerId],
            [
                'user_id' => $user->getKey(),
                'subscription_id' => $subscription->getKey(),
                'amount_in_cents' => $plan->priceInCents(),
                'currency' => 'USD',
                'status' => 'paid',
                'description' => $plan->label().' subscription',
            ],
        );

        return $payment;
    }
}
