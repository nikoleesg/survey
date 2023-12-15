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

    public static function labels(): array
    {
        return [
            1 => 'Single',
            2 => 'Multiple',
            3 => 'Numerical',
            4 => 'Open',
            5 => 'Text',
            6 => 'Calculation',
            7 => 'Matrix',
            9 => 'Dummy',
            11 => 'Datetime',
            12 => 'Date',
            13 => 'Time',
        ];

    }
}
