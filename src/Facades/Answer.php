<?php

namespace Nikoleesg\Survey\Facades;

use Illuminate\Support\Facades\Facade;
use Nikoleesg\Survey\Services\AnswerService;

/**
 * @see \Nikoleesg\Survey\AnswerService
 */
class Answer extends Facade
{
    protected static function getFacadeAccessor()
    {
        return AnswerService::class;
    }
}
