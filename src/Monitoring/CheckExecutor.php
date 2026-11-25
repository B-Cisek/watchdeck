<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Monitoring\Entity\Check;
use App\Monitoring\Entity\CheckResult;
use App\Monitoring\Runner\CheckRunnerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final readonly class CheckExecutor
{
    public function __construct(
        #[AutowireLocator(CheckRunnerInterface::class)]
        private ContainerInterface $runners,
        private EntityManagerInterface $entityManager,
    ) {}

    public function execute(Check $check): CheckResult
    {
        $outcome = $this->runners->get($check->getType()->value)->run($check);
        $checkedAt = new \DateTimeImmutable();

        $check->record($outcome, $checkedAt);
        $result = CheckResult::fromOutcome($check, $outcome, $checkedAt);

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $result;
    }
}
