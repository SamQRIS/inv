<?php

namespace App\Providers;

use App\Services\DeliveryService;
use App\Services\DiscountService;
use App\Services\PaymentService;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton services
        $this->app->singleton(DiscountService::class);
        $this->app->singleton(StockService::class);
 
        // Bound services dengan dependencies
        $this->app->bind(PaymentService::class);
        $this->app->bind(TransactionService::class);
        $this->app->bind(DeliveryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
