<?php

namespace Nikoleesg\Survey\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Nikoleesg\Survey\Enums\VariableTypeEnum;

class VariableData extends Data
{
    public function __construct(
        public string $survey_id,
        public string $name,
        public ?string $label,
        #[WithCast(EnumCast::class)]
        public VariableTypeEnum $type,
        public ?array $codes,
        public ?int $position,
        public ?int $length,
        public ?int $fraction,
        public ?array $formula,
        public ?string $remark,
    ) {}

}
