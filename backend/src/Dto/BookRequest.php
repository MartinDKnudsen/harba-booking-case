<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class BookRequest
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $providerId = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $serviceId = null;

    #[Assert\NotBlank]
    public ?string $startAt = null;
}
