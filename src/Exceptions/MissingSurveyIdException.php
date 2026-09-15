<?php

namespace Nikoleesg\Survey\Exceptions;

class MissingSurveyIdException extends SurveyException
{
    public static function make(): self
    {
        return new self('No survey id: pass one explicitly, call setSurvey(), or set survey.survey_id in the config.');
    }
}
