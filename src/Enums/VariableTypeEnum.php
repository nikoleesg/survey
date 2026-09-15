<?php

namespace Nikoleesg\Survey\Enums;

enum VariableTypeEnum: int
{
    case SINGLE = 1;
    case MULTIPLE = 2;
    case NUMERICAL = 3;
    case OPEN = 4;
    case ALPHA = 5;
    case CALCULABLE = 6;
    case MATRIX = 7;
    case DUMMY = 9;
    case DATETIME = 11;
    case DATE = 12;
    case TIME = 13;

    public function label(): string
    {
        return match ($this)
        {
            VariableTypeEnum::SINGLE => 'Single',
            VariableTypeEnum::MULTIPLE => 'Multiple',
            VariableTypeEnum::NUMERICAL => 'Numerical',
            VariableTypeEnum::OPEN => 'Open',
            VariableTypeEnum::ALPHA => 'Text',
            VariableTypeEnum::CALCULABLE => 'Calculation',
            VariableTypeEnum::MATRIX => 'Matrix',
            VariableTypeEnum::DUMMY => 'Dummy',
            VariableTypeEnum::DATETIME => 'Datetime',
            VariableTypeEnum::DATE => 'Date',
            VariableTypeEnum::TIME => 'Time',
        };
    }

    /**
     * @return array<int, string> value => label
     */
    public static function labels(): array
    {
        return array_column(
            array_map(fn (self $case) => [$case->value, $case->label()], self::cases()),
            1,
            0
        );
    }
}
