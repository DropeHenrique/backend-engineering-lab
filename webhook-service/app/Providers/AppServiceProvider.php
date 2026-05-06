<?php

namespace App\Providers;

use App\Webhooks\GenericWebhookProvider;
use App\Webhooks\GithubWebhookProvider;
use App\Webhooks\HotmartWebhookProvider;
use App\Webhooks\StripeWebhookProvider;
use App\Webhooks\WebhookProviderRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebhookProviderRegistry::class, function () {
            return new WebhookProviderRegistry([
                GenericWebhookProvider::slug() => new GenericWebhookProvider,
                StripeWebhookProvider::slug() => new StripeWebhookProvider,
                GithubWebhookProvider::slug() => new GithubWebhookProvider,
                HotmartWebhookProvider::slug() => new HotmartWebhookProvider,
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
