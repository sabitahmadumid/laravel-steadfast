<?php

namespace SabitAhmad\SteadFast;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SteadFastServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-steadfast')
            ->hasConfigFile()
            ->hasMigration('create_steadfast_logs_table')
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('sabitahmad/laravel-steadfast')
                    ->endWith(function(InstallCommand $command) {
                        $command->info('Have a great day!');
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SteadFast::class, function () {
            return new SteadFast;
        });
    }
}
