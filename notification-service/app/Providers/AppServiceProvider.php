<?php

namespace App\Providers;

use App\Contracts\EventTypeHandler;
use App\Handlers\EventHandlerRegistry;
use App\Handlers\OrderConfirmedHandler;
use App\Handlers\PasswordResetHandler;
use App\Handlers\UserRegisteredHandler;
use App\Notifications\Channels\EmailNotificationChannel;
use App\Notifications\Channels\LogNotificationChannel;
use App\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationDispatcher::class, function (Application $app) {
            return new NotificationDispatcher([
                $app->make(EmailNotificationChannel::class),
                $app->make(LogNotificationChannel::class),
            ]);
        });

        $this->app->singleton(EventHandlerRegistry::class, function (Application $app) {
            /** @var list<EventTypeHandler> $handlers */
            $handlers = [
                $app->make(OrderConfirmedHandler::class),
                $app->make(UserRegisteredHandler::class),
                $app->make(PasswordResetHandler::class),
            ];

            return new EventHandlerRegistry($handlers);
        });
    }

    public function boot(): void
    {
        //
    }
}
