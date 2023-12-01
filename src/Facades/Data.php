<?php

namespace Nikoleesg\Survey\Facades;

use Illuminate\Support\Facades\Facade;
use Nikoleesg\Survey\Services\DataService;

/**
 * @see \Nikoleesg\Survey\DataService
 */
class Data extends Facade
{
    protected static function getFacadeAccessor()
    {
        return DataService::class;
    }
}
