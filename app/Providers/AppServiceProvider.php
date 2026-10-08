<?php

namespace App\Providers;

use App\Ai\CustomProviders;
use App\Ai\OpenAiImageProvider;
use App\Billing\PaymentGateway;
use App\Billing\StripeGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registered here rather than by adding a createOpenaiImageDriver() to
        // the manager, because the manager belongs to the SDK: extend() is the
        // documented seam for a driver the SDK does not ship, and it survives an
        // SDK upgrade in a way that editing the manager would not.
        $this->app->make(AiManager::class)->extend(
            'openai-image',
            fn ($app, array $config): OpenAiImageProvider => new OpenAiImageProvider(
                $config,
                $app->make(Dispatcher::class),
            ),
        );

        // Billing depends on an interface so the checkout route does not change
        // when the provider does, and so a deployment with no keys still boots
        // and simply reports billing as unconfigured.
        $this->app->singleton(PaymentGateway::class, StripeGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(CustomProviders $customProviders): void
    {
        // Custom providers are backed by a table of imported models, so merging
        // them at boot makes the model catalog query that table on any request
        // that renders it. Tests exercise that catalog against a fresh
        // in-memory database, so a developer with an endpoint in their own .env
        // would have every one of those tests fail on a table it never
        // migrated -- a failure with nothing to do with the code under test,
        // and one that changes with whoever happens to be running them.
        //
        // Tests that want the merge call mergeCustomProviders() themselves.
        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->mergeCustomProviders($customProviders);
    }

    /**
     * Add the endpoints declared in WHALE_CUSTOM_PROVIDERS to config('ai.providers').
     *
     * Merging here rather than editing config/ai.php keeps the SDK's own
     * configuration file untouched, so an SDK upgrade that rewrites it cannot
     * drop the merge. A custom provider may not overwrite an existing name:
     * CustomProviders already rejects the reserved ones, so anything left in
     * the way would be a genuine collision and the built-in entry wins.
     *
     * Public because a test declares its own endpoints and needs the same merge
     * the application performs, rather than a second implementation of it.
     */
    public function mergeCustomProviders(CustomProviders $customProviders): void
    {
        $providers = config('ai.providers', []);

        foreach ($customProviders->all() as $provider) {
            if (array_key_exists($provider['name'], $providers)) {
                continue;
            }

            $providers[$provider['name']] = $customProviders->toSdkConfiguration($provider);
        }

        config(['ai.providers' => $providers]);
    }
}
