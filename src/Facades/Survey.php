<?php

namespace Nikoleesg\Survey\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Nikoleesg\Survey\Survey
 */
class Survey extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Nikoleesg\Survey\Survey::class;
    }
}
