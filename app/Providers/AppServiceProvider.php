<?php

namespace App\Providers;

use App\Application\Payment\Contracts\PaymentGateway;
use App\Infrastructure\Payment\FakePaymentGateway;
use App\Infrastructure\Payment\NokashPaymentGateway;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            return match (config('payments.driver')) {
                'fake' => new FakePaymentGateway,
                'nokash' => new NokashPaymentGateway,
                default => throw new InvalidArgumentException('Unsupported payment driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
