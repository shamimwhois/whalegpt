<?php

use App\Billing\PaymentGateway;
use App\Billing\Plan;
use App\Billing\Role;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the pricing page is public', function () {
    $this->get(route('pricing'))->assertOk()->assertSee('Pricing that scales with you')->assertSee('Compare every feature');
});

test('pricing lists every tier with its price', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('Free')
        ->assertSee('Pro')
        ->assertSee('Premium')
        // Prices render as "$19" with "/month" alongside, not "$19.00/mo".
        ->assertSee('$19')
        ->assertSee('$49')
        ->assertSee('/month');
});

test('a new user is on the free plan with no subscription', function () {
    $user = User::factory()->create();

    expect($user->plan())->toBe(Plan::Free)
        ->and($user->role())->toBe(Role::User)
        ->and($user->canUse('chat'))->toBeTrue()
        ->and($user->canUse('audio'))->toBeFalse();
});

test('subscribing a user grants the tier and records it', function () {
    $user = User::factory()->create();

    $user->subscribeTo(Plan::Pro);

    $user->refresh();

    expect($user->plan())->toBe(Plan::Pro)
        ->and($user->canUse('audio'))->toBeTrue()
        ->and(Subscription::where('user_id', $user->id)->count())->toBe(1);
});

test('subscribing twice updates one subscription rather than stacking', function () {
    $user = User::factory()->create();

    $user->subscribeTo(Plan::Pro);
    $user->subscribeTo(Plan::Premium);

    expect(Subscription::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->refresh()->plan())->toBe(Plan::Premium);
});

test('a lapsed subscription falls back to the stored plan', function () {
    $user = User::factory()->create();
    $user->subscribeTo(Plan::Pro);

    $user->subscription()->update(['renews_at' => now()->subDay()]);

    $user->unsetRelation('subscription');

    expect($user->plan())->toBe(Plan::Pro)
        ->and($user->subscription()->first()->isActive())->toBeFalse();
});

test('roles are ordered, with admin outranking staff', function () {
    expect(Role::Admin->includes(Role::Staff))->toBeTrue()
        ->and(Role::Staff->includes(Role::Admin))->toBeFalse()
        ->and(Role::Staff->includes(Role::User))->toBeTrue()
        ->and(Role::User->includes(Role::User))->toBeFalse();
});

test('the staff area refuses ordinary users and admits staff', function () {
    $user = User::factory()->create(['role' => Role::User->value]);

    $this->actingAs($user)->get(route('staff.billing'))->assertForbidden();

    $staff = User::factory()->create(['role' => Role::Staff->value]);
    $this->actingAs($staff)->get(route('staff.billing'))->assertOk();

    $admin = User::factory()->create(['role' => Role::Admin->value]);
    $this->actingAs($admin)->get(route('staff.billing'))->assertOk();
});

test('an unknown role in the database falls back rather than throwing', function () {
    $user = User::factory()->create();
    $user->forceFill(['role' => 'superuser'])->save();

    expect($user->refresh()->role())->toBe(Role::User);
});

test('a payment is recorded once even if the webhook repeats', function () {
    $user = User::factory()->create();
    $payload = ['id' => 'sub_123', 'plan' => 'pro', 'client_reference_id' => $user->id];

    $gateway = app(PaymentGateway::class);

    $gateway->handleWebhook($payload);
    $gateway->handleWebhook($payload);

    expect(Payment::where('provider', 'stripe')->where('provider_id', 'sub_123')->count())->toBe(1)
        ->and($user->refresh()->plan())->toBe(Plan::Pro);
});

test('a webhook for an unknown plan or user changes nothing', function () {
    $user = User::factory()->create();
    $gateway = app(PaymentGateway::class);

    expect($gateway->handleWebhook(['id' => 'x', 'plan' => 'diamond', 'client_reference_id' => $user->id]))->toBeNull()
        ->and($gateway->handleWebhook(['id' => 'y', 'plan' => 'pro', 'client_reference_id' => 99999]))->toBeNull()
        ->and($user->refresh()->plan())->toBe(Plan::Free);
});

test('the webhook refuses a body it cannot verify', function () {
    config(['billing.stripe.webhook_secret' => 'whsec_test']);

    $this->postJson(route('billing.webhook'), ['id' => 'sub_1', 'plan' => 'premium'])
        ->assertForbidden();

    $this->postJson(route('billing.webhook'), [])->assertForbidden();
});

test('the webhook reports itself unconfigured rather than trusting anyone', function () {
    config(['billing.stripe.webhook_secret' => null]);

    $this->postJson(route('billing.webhook'), ['id' => 'sub_1', 'plan' => 'premium'])
        ->assertStatus(501);
});

test('billing status requires an account', function () {
    $this->getJson(route('billing.status'))->assertStatus(401);
});
