<?php

declare(strict_types=1);

namespace App\Tests\Logs\Controller;

use App\Catalog\Entity\Service;
use App\Logs\Entity\LogLine;
use App\Logs\Entity\LogSource;
use App\Logs\Enum\LogStream;
use App\Logs\Security\IngestToken;
use App\Tests\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Events use the field names of Vector's docker_logs source, as the generated config sends them.
 * They are written from Vector's docs, not captured from a real request.
 *
 * @internal
 *
 * @coversNothing
 */
final class IngestLogsControllerTest extends ApiTestCase
{
    private const string DOCKER_ID = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private LogSource $source;
    private IngestToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        $service = new Service(Uuid::v7(), 'shop');
        $this->token = IngestToken::generate();
        $this->source = new LogSource(Uuid::v7(), $service, 'shop', $this->token->hash());
        $this->persist($service, $this->source);
    }

    public function test_stores_lines_sent_with_the_source_token(): void
    {
        $this->send(
            self::event('GET / 200', timestamp: '2026-11-26T10:00:00.123456789Z'),
            self::event('PHP Warning: x', stream: 'stderr', timestamp: '2026-11-26T10:00:01.5Z'),
        );

        self::assertResponseIsSuccessful();
        self::assertSame(['received' => 2, 'stored' => 2], $this->json());

        $lines = $this->lines();
        self::assertCount(2, $lines);
        self::assertSame('GET / 200', $lines[0]->getMessage());
        self::assertSame(LogStream::STDOUT, $lines[0]->getStream());
        self::assertSame(self::DOCKER_ID, $lines[0]->getDockerId());
        // Kept to the microsecond, the most PHP holds.
        self::assertSame('2026-11-26 10:00:00.123456', $lines[0]->getLoggedAt()->format('Y-m-d H:i:s.u'));
        self::assertSame(LogStream::STDERR, $lines[1]->getStream());
    }

    public function test_moves_last_log_at_of_the_source_forward(): void
    {
        $this->send(self::event('later', timestamp: '2026-11-26T10:00:05Z'), self::event('earlier', timestamp: '2026-11-26T10:00:01Z'));
        $this->send(self::event('late batch', timestamp: '2026-11-26T09:00:00Z'));

        $this->entityManager->clear();
        $source = $this->entityManager->find(LogSource::class, $this->source->getId());
        self::assertSame('2026-11-26 10:00:05', $source?->getLastLogAt()?->format('Y-m-d H:i:s'));
    }

    public function test_ignores_a_batch_sent_again(): void
    {
        $batch = [self::event('one'), self::event('two', timestamp: '2026-11-26T10:00:00.000000002Z')];

        $this->send(...$batch);
        $this->send(...$batch);

        self::assertResponseIsSuccessful();
        self::assertSame(['received' => 2, 'stored' => 0], $this->json());
        self::assertCount(2, $this->lines());
    }

    public function test_keeps_identical_lines_that_differ_in_nanoseconds(): void
    {
        $this->send(
            self::event('tick', timestamp: '2026-11-26T10:00:00.000000001Z'),
            self::event('tick', timestamp: '2026-11-26T10:00:00.000000002Z'),
        );

        self::assertCount(2, $this->lines());
    }

    public function test_skips_bad_events_and_keeps_the_rest(): void
    {
        $this->send(
            self::event('good'),
            json_encode(['container_name' => 'shop', 'message' => 'no id, stream or time'], \JSON_THROW_ON_ERROR),
            self::event('bad stream', stream: 'stdin'),
            self::event('bad time', timestamp: 'yesterday'),
            '{not json',
            '"a string, not an object"',
        );

        self::assertResponseIsSuccessful();
        self::assertSame(['received' => 1, 'stored' => 1], $this->json());
        self::assertSame(['good'], array_map(static fn (LogLine $l) => $l->getMessage(), $this->lines()));
    }

    public function test_skips_events_of_another_container(): void
    {
        $this->send(self::event('mine'), self::event('not mine', container: 'shop-db'));

        self::assertSame(['mine'], array_map(static fn (LogLine $l) => $l->getMessage(), $this->lines()));
    }

    public function test_removes_nul_bytes_which_postgresql_rejects(): void
    {
        $this->send(self::event("bin\0ary"));

        self::assertResponseIsSuccessful();
        self::assertSame('binary', $this->lines()[0]->getMessage());
    }

    public function test_accepts_a_json_array_too(): void
    {
        $this->client->request('POST', '/api/ingest/logs', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token->value], content: '['.self::event('one').','.self::event('two', timestamp: '2026-11-26T10:00:01Z').']');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->lines());
    }

    public function test_empty_body_stores_nothing(): void
    {
        $this->send();

        self::assertResponseIsSuccessful();
        self::assertSame(['received' => 0, 'stored' => 0], $this->json());
    }

    public function test_rejects_body_with_no_readable_line(): void
    {
        $this->send('{not json', 'nor this');

        self::assertResponseStatusCodeSame(400);
        self::assertCount(0, $this->lines());
    }

    public function test_rejects_batch_without_token(): void
    {
        $this->client->request('POST', '/api/ingest/logs', server: ['CONTENT_TYPE' => 'application/x-ndjson'], content: self::event('anonymous'));

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->lines());
    }

    public function test_rejects_batch_with_unknown_token(): void
    {
        $this->sendWith(IngestToken::generate()->value, self::event('forged'));

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->lines());
    }

    public function test_token_writes_to_its_own_source_only(): void
    {
        $other = new LogSource(Uuid::v7(), $this->source->getService(), 'shop-db', IngestToken::generate()->hash());
        $this->persist($other);

        // An event of shop-db sent with the token of shop is skipped, not stored under shop-db.
        $this->send(self::event('mine'), self::event('not mine', container: 'shop-db'));

        $lines = $this->lines();
        self::assertSame(['mine'], array_map(static fn (LogLine $l) => $l->getMessage(), $lines));
        self::assertSame($this->source->getId()->toRfc4122(), $lines[0]->getLogSource()->getId()->toRfc4122());
    }

    private static function event(
        string $message,
        string $stream = 'stdout',
        string $timestamp = '2026-11-26T10:00:00.000000001Z',
        string $container = 'shop',
    ): string {
        return json_encode([
            'container_id' => self::DOCKER_ID,
            'container_name' => $container,
            'message' => $message,
            'stream' => $stream,
            'timestamp' => $timestamp,
        ], \JSON_THROW_ON_ERROR);
    }

    private function send(string ...$lines): void
    {
        $this->sendWith($this->token->value, ...$lines);
    }

    private function sendWith(string $token, string ...$lines): void
    {
        $this->client->request('POST', '/api/ingest/logs', server: [
            'CONTENT_TYPE' => 'application/x-ndjson',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: implode("\n", $lines));
    }

    /** @return list<LogLine> */
    private function lines(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(LogLine::class)->findBy([], ['loggedAt' => 'ASC', 'id' => 'ASC']);
    }
}
