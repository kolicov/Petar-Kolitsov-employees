<?php

namespace App\Providers;

use App\Enums\DateOrder;
use App\Services\DateNames;
use App\Services\DateParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DateNames::class, fn (): DateNames => new DateNames(
            config('employees.month_name_locales'),
        ));

        $this->app->singleton(DateParser::class, fn (): DateParser => new DateParser(
            $this->app->make(DateNames::class),
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
