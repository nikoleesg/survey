<?php

namespace Nikoleesg\Survey\Exceptions;

use RuntimeException;

class NoDataLoadedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No data loaded: call one of the get*FromFile() methods before persist().');
    }
}
