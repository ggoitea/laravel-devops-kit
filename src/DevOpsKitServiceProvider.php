<?php

namespace Ggoitea\LaravelDevopsKit;

use Ggoitea\LaravelDevopsKit\Console\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;

class DevOpsKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            InstallCommand::class,
        ]);
    }
}
