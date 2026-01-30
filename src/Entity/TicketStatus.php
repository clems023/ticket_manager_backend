<?php

declare(strict_types=1);

namespace App\Entity;

enum TicketStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DONE = 'DONE';
}
