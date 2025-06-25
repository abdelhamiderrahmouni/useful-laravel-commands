<?php

declare(strict_types=1);

test('service providers')
    ->expect('UsefulLaravelCommands\UsefulLaravelCommandsServiceProvider')
    ->toOnlyUse([
        'Illuminate\Contracts\Support\DeferrableProvider',
        'Illuminate\Support\ServiceProvider',
    ]);
