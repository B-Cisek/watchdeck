<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ServiceGroup;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceGroupResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
    ) {
    }

    public static function fromEntity(ServiceGroup $group): self
    {
        return new self($group->getId(), $group->getName());
    }
}
