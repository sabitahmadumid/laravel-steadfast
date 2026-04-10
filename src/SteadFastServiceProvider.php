<?php

namespace SabitAhmad\SteadFast;

use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;
use SabitAhmad\SteadFast\Commands\SteadfastCleanupCommand;
use SabitAhmad\SteadFast\Commands\SteadfastStatsCommand;
use SabitAhmad\SteadFast\Commands\SteadfastTestCommand;
use SabitAhmad\SteadFast\Services\SteadfastFraudChecker;
use SabitAhmad\SteadFast\Services\SteadfastHttpClient;
use SabitAhmad\SteadFast\Services\SteadfastLogger;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SteadFastServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-steadfast')
            ->hasConfigFile()
            ->hasMigration('create_steadfast_logs_table')
            ->hasCommands([
                SteadfastTestCommand::class,
                SteadfastStatsCommand::class,
                SteadfastCleanupCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('sabitahmad/laravel-steadfast')
                    ->endWith(function (InstallCommand $command) {
                        $command->info('Laravel SteadFast installed.');
                        $command->info('Set `STEADFAST_API_KEY` and `STEADFAST_SECRET_KEY`, then run `php artisan steadfast:test`.');
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SteadfastLogger::class, function ($app) {
            return new SteadfastLogger(
                logger: $app->make(LoggerInterface::class),
                config: $app['config']->get('steadfast', []),
            );
        });

        $this->app->singleton(SteadfastHttpClient::class, function ($app) {
            return new SteadfastHttpClient(
                http: $app->make(HttpFactory::class),
                logger: $app->make(SteadfastLogger::class),
                config: $app['config']->get('steadfast', []),
            );
        });

        $this->app->singleton(SteadfastFraudChecker::class, function ($app) {
            return new SteadfastFraudChecker(
                http: $app->make(HttpFactory::class),
                logger: $app->make(SteadfastLogger::class),
                fallbackLogger: $app->make(LoggerInterface::class),
                config: $app['config']->get('steadfast', []),
            );
        });

        $this->app->singleton(SteadFast::class, function ($app) {
            return new SteadFast(
                httpClient: $app->make(SteadfastHttpClient::class),
                logger: $app->make(SteadfastLogger::class),
                fraudChecker: $app->make(SteadfastFraudChecker::class),
            );
        });
    }
}
