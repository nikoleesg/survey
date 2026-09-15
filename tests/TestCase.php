<?php

namespace Nikoleesg\Survey\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Nikoleesg\Survey\SurveyServiceProvider;
use Spatie\EloquentSortable\EloquentSortableServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\Sluggable\SluggableServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Nikoleesg\\Survey\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelDataServiceProvider::class,
            SluggableServiceProvider::class,
            EloquentSortableServiceProvider::class,
            SurveyServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (glob(__DIR__.'/../database/migrations/*.php.stub') as $stub) {
            (include $stub)->up();
        }
    }
}
