<?php

declare(strict_types=1);

namespace App\Command;

use App\CheckExecutor;
use App\Repository\CheckRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand('app:checks:run')]
#[AsPeriodicTask(frequency: '10 seconds')]
final readonly class ChecksRunCommand
{
    public function __construct(
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
        private CheckExecutor $executor,
        private CheckRepository $checks
    )
    {
    }

    public function __invoke(): int {
        $lock = $this->lockFactory->createLock('checks-run');

        if (!$lock->acquire()) {
            return Command::SUCCESS;
        }

        try {
            foreach ($this->checks->findDue(new \DateTimeImmutable()) as $check) {
                try {
                    $this->executor->execute($check);
                } catch (\Throwable $e) {
                    $this->logger->error('Check could not be executed', [
                        'check' => $check->getId()->toRfc4122(),
                        'exception' => $e,
                    ]);
                }
            }
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
