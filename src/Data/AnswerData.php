<?php

namespace Nikoleesg\Survey\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Nikoleesg\Survey\Data\Transformers\JsonableTransformer;

class AnswerData extends Data
{
    public function __construct(
        public string $survey_id,
        public int $variable_id,
        public int $interview_number,
        #[WithTransformer(JsonableTransformer::class)]
        public array $result
//        public int $type,
//        public ?int $code_answer,
//        public ?bool $boolean_answer,
//        public ?float $number_answer,
//        #[WithCast(DateTimeInterfaceCast::class)]
//        public ?DateTime $date_answer,
//        #[WithCast(DateTimeInterfaceCast::class)]
//        public ?DateTime $time_answer,
//        #[WithCast(DateTimeInterfaceCast::class)]
//        public ?DateTime $datetime_answer,
    ) {}

}
