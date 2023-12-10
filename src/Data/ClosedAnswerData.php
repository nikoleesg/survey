<?php

namespace Nikoleesg\Survey\Data;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Nikoleesg\Survey\Data\Casts\CarbonCast;

class ClosedAnswerData extends Data
{
    public function __construct(
        public int $interview_number,
        public int $sub_questionnaire_number,
        public int $interview_time_in_seconds,
        public int $number_of_screens_shown,
        public ?string $interviewer_id,
        #[WithCast(CarbonCast::class)]
        public Carbon $last_contact_datetime
    ) {}

    public static function fromData(string $dataString): self
    {
        return new self(
            intval(Str::substr($dataString, 0, 8)),
            intval(Str::substr($dataString, 8, 2)),
            intval(Str::substr($dataString, 10, 5)),
            intval(Str::substr($dataString, 15, 4)),
            Str::substr($dataString, 20, 8),
            Carbon::parse(Str::substr($dataString, 28, 12)),
        );
    }

}
