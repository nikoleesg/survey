<?php

namespace Nikoleesg\Survey\Data;

use Spatie\LaravelData\Data;

class ParadataData extends Data
{
    public function __construct(
        public int $interview_number,
        public string $label,
        public ?string $result,
        public string $survey_id,
    ) {}

}
