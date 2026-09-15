<?php

namespace Nikoleesg\Survey\Exceptions;

use Nikoleesg\Survey\Models\Variable;

class UnparseableColumnException extends SurveyException
{
    public static function make(int $interviewNumber, Variable $variable, string $format, string $content): self
    {
        return new self(sprintf(
            'Interview %d, variable "%s": column value "%s" does not match the %s format "%s".',
            $interviewNumber,
            $variable->name,
            $content,
            $variable->type->label(),
            $format
        ));
    }
}
