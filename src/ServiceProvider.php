<?php

namespace Iagofelicio\GeoAnalytics;

use Statamic\Statamic;
use Statamic\Facades\Utility;
use Illuminate\Filesystem\Filesystem;
use Statamic\Providers\AddonServiceProvider;
use Iagofelicio\GeoAnalytics\Models\GeoAnalytics;
use Iagofelicio\GeoAnalytics\Commands\ProcessRequests;
use Iagofelicio\GeoAnalytics\Middleware\GeoAnalyticsMiddleware;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $middlewareGroups = [
        'web' => [
            GeoAnalyticsMiddleware::class
        ],
    ];

    protected $commands = [
        ProcessRequests::class,
    ];

    protected function schedule($schedule)
    {
        $schedule->command('requests:process')->everyFifteenSeconds()->withoutOverlapping();
        $schedule->command('requests:process --force-update')->dailyAt('00:01')->withoutOverlapping();
    }

    public function bootAddon()
    {
        Statamic::afterInstalled(function () {

            #Create required application directories
            GeoAnalytics::init_directories();

            #Initiate application profile
            GeoAnalytics::init_profile();

            #Start application cache
            GeoAnalytics::update_cache();

        });

        Utility::extend(function () {
            Utility::register("Geo Analytics")
                ->title("Geo Analytics")
                ->navTitle("Geo Analytics")
                ->icon('earth')
                ->description('Track requests to your site.')
                ->view('geo-analytics::utility');
        });
    }

}
