<?php

namespace Titus\Beatle;

use Illuminate\Support\ServiceProvider;
use Titus\Beatle\Classes\APIClass;
use Titus\Beatle\Classes\AuthHandler;

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

        $this->app->bind('APIHandler',function() {
            return new APIClass;
        });

        // $this->app->bind('AuthHandler',function() {
        //     return new AuthHandler;
        // });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    { 
        // $this->publishes([
        //     __DIR__.'/../database/migrations/' => database_path('migrations')
        // ], 'migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');


        if (file_exists($file = app_path('Helpers/helpers.php')))
        {
            require $file;
        }
    }
}
