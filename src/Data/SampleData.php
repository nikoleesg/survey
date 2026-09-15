<?php

namespace Nikoleesg\Survey\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Transformers\EnumTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Nikoleesg\Survey\Data\Casts\CarbonCast;
use Nikoleesg\Survey\Enums\ChannelEnum;
use Nikoleesg\Survey\Enums\InterruptIndicationEnum;

/**
 * The system header of one closed answer file record (columns 1-60), i.e.
 * one row of survey_samples. Property names are the table columns so the
 * collection can be upserted as-is.
 */
class SampleData extends Data
{
    // "!" zeroes the fields the format does not carry (seconds), which
    // createFromFormat() would otherwise fill from the current time
    public const LAST_CONTACT_FORMAT = '!YmdHi';

    public function __construct(
        public string $survey_id,
        public int $interview_number,
        public ?int $sub_questionnaire_number,
        public ?int $interview_time_in_seconds,
        public ?int $number_of_screens_shown,
        #[WithCast(EnumCast::class), WithTransformer(EnumTransformer::class)]
        public ?InterruptIndicationEnum $interrupt_indication,
        public ?string $interviewer_id,
        #[WithCast(CarbonCast::class), WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $last_contact_at,
        public ?string $odin_version,
        public ?int $idle_time,
        public ?int $week_number,
        public ?int $week_version_number,
        public ?int $family_member_number,
        #[WithCast(EnumCast::class), WithTransformer(EnumTransformer::class)]
        public ?ChannelEnum $channel,
    ) {}

    /**
     * Parse the header by byte offset (the file is ASCII up to the answer
     * data). A blank field is null; a record shorter than 60 columns simply
     * has nulls for the missing fields.
     */
    public static function fromRow(string $surveyId, string $row): self
    {
        return new self(
            survey_id:                 $surveyId,
            interview_number:          (int)substr($row, 0, 8),
            sub_questionnaire_number:  self::intOrNull($row, 8, 2),
            interview_time_in_seconds: self::intOrNull($row, 10, 5),
            number_of_screens_shown:   self::intOrNull($row, 15, 4),
            interrupt_indication:      InterruptIndicationEnum::tryFrom(self::intOrNull($row, 19, 1) ?? 0),
            interviewer_id:            self::stringOrNull($row, 20, 8),
            last_contact_at:           self::lastContactOrNull($row),
            // column 41 is always "0"
            odin_version:              self::stringOrNull($row, 41, 7),
            idle_time:                 self::intOrNull($row, 48, 5),
            week_number:               self::intOrNull($row, 53, 2),
            week_version_number:       self::intOrNull($row, 55, 2),
            family_member_number:      self::intOrNull($row, 57, 2),
            channel:                   ChannelEnum::tryFrom(self::intOrNull($row, 59, 1) ?? 0),
        );
    }

    protected static function stringOrNull(string $row, int $offset, int $length): ?string
    {
        $value = trim(substr($row, $offset, $length));

        return $value === '' ? null : $value;
    }

    protected static function intOrNull(string $row, int $offset, int $length): ?int
    {
        $value = self::stringOrNull($row, $offset, $length);

        return $value === null ? null : (int)$value;
    }

    /**
     * Columns 29-40, "date and time last contact" as YYYYMMDDHHMM.
     */
    protected static function lastContactOrNull(string $row): ?Carbon
    {
        $value = self::stringOrNull($row, 28, 12);

        return $value === null ? null : Carbon::createFromFormat(self::LAST_CONTACT_FORMAT, $value);
    }
}
