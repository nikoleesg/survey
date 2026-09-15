<?php

namespace Nikoleesg\Survey\Exceptions;

class UnreadableFileException extends SurveyException
{
    public static function make(string $fileName, string $loader): self
    {
        return new self(sprintf('%s: cannot read file "%s".', $loader, $fileName));
    }
}
