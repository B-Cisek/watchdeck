<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Repository\ServiceGroupRepository;
use App\Shared\Doctrine\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ServiceGroupRepository::class)]
#[ORM\Table(name: '`service_groups`')]
class ServiceGroup
{
    use TimestampTrait;

    /** @var Collection<int, Service> */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'group')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $services;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private Uuid $id,
        #[ORM\Column(length: 100)]
        private string $name
    )
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->services = new ArrayCollection();
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

    /** @return Collection<int, Service> */
    public function getServices(): Collection
    {
        return $this->services;
    }
}
