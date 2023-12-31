<?php

namespace Nikoleesg\Survey;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Nikoleesg\Survey\Commands\SurveyCommand;

class SurveyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('survey')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_answers_table',
                'create_open_answers_table',
                'create_paradatas_table',
                'create_variables_table'
            ])
            ->hasCommand(SurveyCommand::class);
    }
}
