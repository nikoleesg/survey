<?php

namespace Nikoleesg\Survey\Data;

use Spatie\LaravelData\Data;

class OpenAnswerData extends Data
{
    public function __construct(
        public int $interview_number,
        public int $sub_questionnaire_number,
        public int $position,
        public int $length,
        public ?int $code_number,
        public string $verbatim_text,
        public string $survey_id,
    ) {}

}
