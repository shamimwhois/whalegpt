<?php

namespace App\Http\Controllers;

use App\Billing\PaymentGateway;
use App\Billing\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * The pricing page and everything behind it.
 *
 * The page is public: a visitor has to be able to read what a plan costs before
 * deciding whether to sign in. Checkout and the portal require an account,
 * because a payment has to belong to somebody.
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * The public pricing page.
     */
    public function index(Request $request): View
    {
        return view('billing.pricing', [
            'plans' => Plan::cases(),
            'gateway' => $this->gateway->available(),
            'current' => $request->user()?->plan(),
            'role' => $request->user()?->role(),
            'canceled' => $request->boolean('canceled'),
        ]);
    }

    /**
     * Where the user stands right now.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $subscription = $user->subscription()->first();

        return response()->json([
            'plan' => $user->plan()->value,
            'plan_label' => $user->plan()->label(),
            'role' => $user->role()->value,
            'features' => $user->plan()->features(),
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'active' => $subscription->isActive(),
                'renews_at' => $subscription->renews_at?->toIso8601String(),
                'provider' => $subscription->provider,
            ],
            'payments' => $user->payments()
                ->latest()
                ->limit(10)
                ->get(['amount_in_cents', 'currency', 'status', 'description', 'created_at'])
                ->toArray(),
            'gateway_available' => $this->gateway->available(),
        ]);
    }

    /**
     * Start a checkout for a paid plan.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(['pro', 'premium'])],
        ]);

        $plan = Plan::from($validated['plan']);

        try {
            return redirect()->away($this->gateway->checkout($request->user(), $plan));
        } catch (Throwable $e) {
            Log::info('Checkout could not start: '.$e->getMessage());

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Hand the user to the provider to manage or cancel.
     */
    public function portal(Request $request): RedirectResponse
    {
        try {
            return redirect()->away($this->gateway->portal($request->user()));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Where the provider sends the user after a successful checkout.
     *
     * The tier itself is granted by the webhook, not here: a browser can be
     * closed before the redirect arrives, and a success page that granted access
     * would hand out a paid tier for free.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        // The webhook may not have landed yet, so refresh once before deciding.
        $user->unsetRelation('subscription');

        return view('billing.success', [
            'plan' => $user->plan(),
            'pending' => ! $user->subscription()->exists(),
        ]);
    }

    /**
     * Apply a provider event.
     *
     * The signature is verified before the body is trusted at all: without that
     * anyone could post a fake "payment succeeded" and grant themselves a tier.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('billing.stripe.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhooks are not configured.'], 501);
        }

        $payload = $request->json()->all();
        $signature = (string) $request->header('Stripe-Signature', '');

        if (! $this->validSignature((string) $request->getContent(), $signature, $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payment = $this->gateway->handleWebhook($payload);

        return response()->json(['received' => true, 'applied' => $payment !== null]);
    }

    /**
     * Compare the provider's signature against the raw body.
     *
     * A plain hash_equals is used rather than === so the comparison takes the
     * same time whatever the attacker sends.
     */
    private function validSignature(string $payload, string $header, string $secret): bool
    {
        $timestamp = null;

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1') {
                $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

                if (hash_equals($expected, $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
