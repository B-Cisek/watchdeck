<?php

declare(strict_types=1);

namespace App\Catalog\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateServiceRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $name,
        // Not a Uuid object: the serializer reports every malformed UUID as "should be of type string".
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Uuid]
        public ?string $groupId = null,
    ) {}
}
