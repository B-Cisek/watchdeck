<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ServiceRepository;
use App\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: '`services`')]
class Service
{
    use TimestampTrait;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private Uuid $id,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\ManyToOne(inversedBy: 'services')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?ServiceGroup $group = null
    )
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getGroup(): ?ServiceGroup
    {
        return $this->group;
    }

    public function setGroup(?ServiceGroup $group): static
    {
        $this->group = $group;

        return $this;
    }
}
