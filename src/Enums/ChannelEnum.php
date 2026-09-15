<?php

namespace Nikoleesg\Survey\Enums;

/**
 * Column 60 of the closed answer file: how the interview was conducted.
 */
enum ChannelEnum: int
{
    case CATI = 1;
    case CAWI = 2;
    case CAPI = 3;
    case CASI = 4;

    public function label(): string
    {
        return match ($this) {
            ChannelEnum::CATI => 'CATI',
            ChannelEnum::CAWI => 'CAWI (Web)',
            ChannelEnum::CAPI => 'CAPI',
            ChannelEnum::CASI => 'CASI (Panel)',
        };
    }
}
