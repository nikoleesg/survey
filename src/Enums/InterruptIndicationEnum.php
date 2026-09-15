<?php

namespace Nikoleesg\Survey\Enums;

/**
 * Column 20 of the closed answer file; blank when the interview completed.
 */
enum InterruptIndicationEnum: int
{
    case BROKEN_OFF = 1;
    case APPOINTMENT_MADE = 2;

    public function label(): string
    {
        return match ($this) {
            InterruptIndicationEnum::BROKEN_OFF => 'Interview broken off',
            InterruptIndicationEnum::APPOINTMENT_MADE => 'Appointment made',
        };
    }
}
