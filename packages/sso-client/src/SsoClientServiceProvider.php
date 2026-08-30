<?php

namespace Sttc\SsoClient;

use Illuminate\Support\ServiceProvider;

class SsoClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sso-client.php', 'sso-client');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sso-client.php' => $this->app->configPath('sso-client.php'),
        ], 'sso-client-config');
    }
}
