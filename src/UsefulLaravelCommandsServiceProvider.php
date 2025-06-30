<?php

declare(strict_types=1);

namespace UsefulLaravelCommands;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use UsefulLaravelCommands\Console\Commands\CleanDevFiles;
use UsefulLaravelCommands\Console\Commands\ImagesCompressCommand;
use UsefulLaravelCommands\Console\Commands\ImagesOptimizeCommand;
use UsefulLaravelCommands\Console\Commands\LangFilesToJson;
use UsefulLaravelCommands\Console\Commands\ResetUserPassword;
use UsefulLaravelCommands\Console\Commands\TranslateCommand;

class UsefulLaravelCommandsServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/useful-commands.php' => config_path('useful-commands.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanDevFiles::class,
                ImagesCompressCommand::class,
                ImagesOptimizeCommand::class,
                LangFilesToJson::class,
                ResetUserPassword::class,
                TranslateCommand::class
            ]);
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'useful-laravel-commands');
    }
}
