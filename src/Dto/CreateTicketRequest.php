<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre est requis.')]
        #[Assert\Length(min: 5, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.', max: 255)]
        public string $title = '',

        #[Assert\Length(max: 65535)]
        public string $description = '',

        /** Valeurs autorisées : OPEN, IN_PROGRESS, DONE */
        public ?string $status = null,

        /** Valeurs autorisées : LOW, MEDIUM, HIGH */
        public ?string $priority = null,
    ) {
    }
}
