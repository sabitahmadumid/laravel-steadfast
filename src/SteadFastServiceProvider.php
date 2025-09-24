<?php

namespace SabitAhmad\SteadFast;

use SabitAhmad\SteadFast\Commands\SteadfastCleanupCommand;
use SabitAhmad\SteadFast\Commands\SteadfastStatsCommand;
use SabitAhmad\SteadFast\Commands\SteadfastTestCommand;
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
                        $command->info('🚀 Laravel SteadFast package installed successfully!');
                        $command->info('');
                        $command->info('Next steps:');
                        $command->info('1. Add your API credentials to .env file:');
                        $command->info('   STEADFAST_API_KEY=your_api_key');
                        $command->info('   STEADFAST_SECRET_KEY=your_secret_key');
                        $command->info('');
                        $command->info('2. Test your configuration:');
                        $command->info('   php artisan steadfast:test');
                        $command->info('');
                        $command->info('3. View usage statistics:');
                        $command->info('   php artisan steadfast:stats');
                        $command->info('');
                        $command->info('Happy shipping! 📦');
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SteadFast::class, function () {
            return new SteadFast;
        });
    }

    public function packageBooted(): void
    {
        // Register model pruning if enabled
        if (config('steadfast.logging.cleanup_logs', true)) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->command('model:prune', ['--model' => \SabitAhmad\SteadFast\Models\SteadfastLog::class])
                    ->daily()
                    ->when(config('steadfast.logging.enabled', true));
            });
        }
    }
}
