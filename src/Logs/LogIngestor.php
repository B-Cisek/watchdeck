<?php

declare(strict_types=1);

namespace App\Logs;

use App\Logs\Entity\LogSource;
use App\Logs\Repository\LogLineRepository;
use App\Logs\Repository\LogSourceRepository;
use App\Logs\Vector\VectorLogEvent;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final readonly class LogIngestor
{
    public function __construct(
        private Connection $connection,
        private LogLineRepository $logLines,
        private LogSourceRepository $logSources,
        private LoggerInterface $logger,
    ) {}

    /**
     * Stores the whole batch or nothing, so a retried batch is never half there.
     *
     * @param list<VectorLogEvent> $events
     *
     * @return int lines stored, duplicates excluded
     */
    public function ingest(LogSource $source, array $events): int
    {
        $rows = [];
        $lastLogAt = null;

        foreach ($events as $event) {
            // Guards against a config edited by hand to send other containers to this source.
            if ($event->containerName !== $source->getContainerName()) {
                $this->logger->warning('Skipped log event of container {container} sent to log source {source}', [
                    'container' => $event->containerName,
                    'source' => $source->getId()->toRfc4122(),
                ]);

                continue;
            }

            $loggedAt = new \DateTimeImmutable($event->timestamp)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            if ($lastLogAt === null || $loggedAt > $lastLogAt) {
                $lastLogAt = $loggedAt;
            }

            $rows[] = [
                'log_source_id' => $source->getId()->toRfc4122(),
                'docker_id' => $event->containerId,
                'logged_at' => $loggedAt->format('Y-m-d H:i:s.u'),
                'stream' => $event->stream,
                // PostgreSQL rejects the NUL byte in text columns.
                'message' => str_replace("\0", '', $event->message),
                'event_hash' => $this->hash($source, $event),
            ];
        }

        if ($lastLogAt === null) {
            return 0;
        }

        return $this->connection->transactional(function () use ($source, $rows, $lastLogAt): int {
            $inserted = $this->logLines->insertIgnoringDuplicates($rows);
            $this->logSources->touchLastLogAt($source, $lastLogAt);

            return $inserted;
        });
    }

    /** From the raw timestamp: two lines in the same microsecond still differ in nanoseconds. */
    private function hash(LogSource $source, VectorLogEvent $event): string
    {
        return hash('sha256', implode("\0", [
            $source->getId()->toRfc4122(),
            $event->containerId,
            $event->timestamp,
            $event->stream,
            $event->message,
        ]));
    }
}
