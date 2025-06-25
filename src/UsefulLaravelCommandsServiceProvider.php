<?php

declare(strict_types=1);

namespace UsefulLaravelCommands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use UsefulLaravelCommands\Console\Commands\ResetUserPassword;

class UsefulLaravelCommandsServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ResetUserPassword::class,
            ]);
        }
    }
}
