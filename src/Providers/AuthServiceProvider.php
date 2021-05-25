<?php

namespace Titus\Beatle\Providers;

use Illuminate\Support\ServiceProvider;
use Titus\Beatle\Classes\APIClass;
use Titus\Beatle\Classes\AuthHandler;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    { 

        $this->app->bind('AuthHandler',function() {
            return new AuthHandler;
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {  

    }
}
