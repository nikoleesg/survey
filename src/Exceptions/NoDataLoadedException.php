<?php

namespace Nikoleesg\Survey\Exceptions;

class NoDataLoadedException extends SurveyException
{
    public static function make(): self
    {
        return new self('No data loaded: call one of the get*FromFile() methods before persist().');
    }
}
