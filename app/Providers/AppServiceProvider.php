<?php

namespace App\Providers;

use App\Models\Listing;
use App\Observers\ListingObserver;
use App\Services\Onhost\OnhostCreditView;
use App\Services\Payments\ComgatePaymentProvider;
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
            'comgate' => ComgatePaymentProvider::fromConfig(),
            default => new StubPaymentProvider,
        });
        $this->app->scoped(OnhostCreditView::class);
    }

    public function boot(): void
    {
        View::composer(['pages.pricing', 'partials.onhost-credit'], OnhostCreditView::class);
        Listing::observe(ListingObserver::class);
        Paginator::defaultView('vendor.pagination.simple-cz');
        RateLimiter::for('contact', fn (Request $r) => [Limit::perMinute(3)->by($r->ip()), Limit::perDay(30)->by($r->ip())]);
        RateLimiter::for('geocode', fn (Request $r) => [Limit::perMinute(40)->by('u'.$r->user()?->id), Limit::perDay(1000)->by('u'.$r->user()?->id)]);
        RateLimiter::for('contract-code', fn (Request $r) => [Limit::perMinute(3)->by('cc'.$r->user()?->id), Limit::perHour(10)->by('cch'.$r->user()?->id)]);
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(6)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
    }
}
