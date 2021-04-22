<?php

namespace Titus\Beatle;

use Illuminate\Support\ServiceProvider;

class MyPackageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('Titus\Beatle\Greeter');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    { 
        $this->publishes([
            __DIR__.'../database/migrations/' => database_path('migrations')
        ], 'migrations');
    }
}
