<?php

declare(strict_types=1);

namespace App\Catalog\Dto;

use App\Catalog\Entity\Service;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public ?ServiceGroupResponse $group,
    ) {}

    public static function fromEntity(Service $service): self
    {
        $group = $service->getGroup();

        return new self(
            $service->getId(),
            $service->getName(),
            $group === null ? null : ServiceGroupResponse::fromEntity($group),
        );
    }
}
