<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email est requis.')]
        #[Assert\Email(message: 'L\'email "{{ value }}" n\'est pas valide.')]
        public string $email = '',

        #[Assert\NotBlank(message: 'Le mot de passe est requis.')]
        public string $password = '',
    ) {
    }
}
