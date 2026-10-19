<?php

namespace App\Providers;

use App\Services\Payments\PaymentProvider;
use App\Services\Payments\StubPaymentProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProvider::class, fn () => match (config('reality.payments.provider')) {
            // 'comgate' => new ComgatePaymentProvider(...), // TODO: implement behind the same contract
            default => new StubPaymentProvider,
        });
        $this->app->scoped(\App\Services\Onhost\OnhostCreditView::class);
    }

    public function boot(): void
    {
        View::composer(['pages.pricing', 'partials.onhost-credit'], \App\Services\Onhost\OnhostCreditView::class);
        Paginator::defaultView('vendor.pagination.simple-cz');
        RateLimiter::for('contact', fn (Request $r) => [Limit::perMinute(3)->by($r->ip()), Limit::perDay(30)->by($r->ip())]);
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(6)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
    }
}
