<?php

namespace App\Providers;

use App\Enums\DateOrder;
use App\Services\DateParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DateParser::class, fn (): DateParser => new DateParser(
            DateOrder::from(config('employees.ambiguous_date_order')),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
