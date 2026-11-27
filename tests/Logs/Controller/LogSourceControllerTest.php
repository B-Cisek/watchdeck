<?php

declare(strict_types=1);

namespace App\Tests\Logs\Controller;

use App\Catalog\Entity\Service;
use App\Logs\Entity\LogLine;
use App\Logs\Entity\LogSource;
use App\Logs\Repository\LogLineRepository;
use App\Logs\Security\IngestToken;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @coversNothing
 */
final class LogSourceControllerTest extends ApiTestCase
{
    public function test_requires_authentication(): void
    {
        $this->client->jsonRequest('POST', '/api/services/01935e5c-0000-7000-8000-000000000000/log-sources', ['containerName' => 'shop']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_adds_log_source_to_service(): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);
        $serviceId = $service->getId()->toRfc4122();

        $this->client->jsonRequest('POST', "/api/services/{$serviceId}/log-sources", ['containerName' => 'shop-app', 'host' => 'vps-1']);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        $sourceId = $payload['logSource']['id'];
        self::assertSame([
            'id' => $sourceId,
            'serviceId' => $serviceId,
            'containerName' => 'shop-app',
            'host' => 'vps-1',
            'lastLogAt' => null,
        ], $payload['logSource']);

        $this->entityManager->clear();
        $source = $this->entityManager->find(LogSource::class, Uuid::fromString($sourceId));
        self::assertSame($serviceId, $source?->getService()->getId()->toRfc4122());
        self::assertSame('shop-app', $source->getContainerName());
    }

    public function test_returns_vector_config_for_the_container(): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);

        $this->client->jsonRequest('POST', "/api/services/{$service->getId()->toRfc4122()}/log-sources", ['containerName' => 'shop']);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        $sourceId = $payload['logSource']['id'];
        $key = 'watchdeck_'.str_replace('-', '_', $sourceId);
        self::assertSame("watchdeck-{$sourceId}.yaml", $payload['vectorConfig']['fileName']);

        $config = Yaml::parse($payload['vectorConfig']['content']);
        self::assertSame(['shop'], $config['sources']["{$key}_docker"]['include_containers']);
        // Without it "shop" would also take the logs of "shop-db".
        self::assertSame('.container_name == "shop"', $config['transforms']["{$key}_exact"]['condition']);
        self::assertSame(["{$key}_exact"], $config['sinks'][$key]['inputs']);
        self::assertSame('http://localhost/api/ingest/logs', $config['sinks'][$key]['uri']);
        self::assertSame('bearer', $config['sinks'][$key]['auth']['strategy']);
        self::assertSame('newline_delimited', $config['sinks'][$key]['framing']['method']);
    }

    public function test_token_in_the_vector_config_lets_vector_send_logs(): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);
        $this->client->jsonRequest('POST', "/api/services/{$service->getId()->toRfc4122()}/log-sources", ['containerName' => 'shop']);
        $sink = array_values(Yaml::parse($this->json()['vectorConfig']['content'])['sinks'])[0];

        $this->client->request('POST', $sink['uri'], server: [
            'CONTENT_TYPE' => 'application/x-ndjson',
            'HTTP_AUTHORIZATION' => 'Bearer '.$sink['auth']['token'],
        ], content: json_encode([
            'container_id' => 'abc',
            'container_name' => 'shop',
            'message' => 'hello',
            'stream' => 'stdout',
            'timestamp' => '2026-11-26T10:00:00Z',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame(['received' => 1, 'stored' => 1], $this->json());
    }

    public function test_stores_only_the_hash_of_the_token(): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);
        $this->client->jsonRequest('POST', "/api/services/{$service->getId()->toRfc4122()}/log-sources", ['containerName' => 'shop']);
        $token = array_values(Yaml::parse($this->json()['vectorConfig']['content'])['sinks'])[0]['auth']['token'];

        $stored = $this->entityManager->getConnection()->fetchOne('SELECT ingest_token_hash FROM log_sources');

        self::assertSame(new IngestToken($token)->hash(), $stored);
        self::assertNotSame($token, $stored);
    }

    public function test_blank_host_is_stored_as_null(): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);

        $this->client->jsonRequest('POST', "/api/services/{$service->getId()->toRfc4122()}/log-sources", ['containerName' => 'shop', 'host' => '  ']);

        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->json()['logSource']['host']);
    }

    #[TestWith([''])]
    #[TestWith(['-shop'])]
    #[TestWith(['shop app'])]
    #[TestWith(['shop"; .x == "'])]
    public function test_rejects_invalid_container_name(string $containerName): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);

        $this->client->jsonRequest('POST', "/api/services/{$service->getId()->toRfc4122()}/log-sources", ['containerName' => $containerName]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('containerName', $this->json()['violations'][0]['propertyPath']);
        self::assertSame(0, $this->entityManager->getRepository(LogSource::class)->count());
    }

    public function test_unknown_service_is_not_found(): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', '/api/services/01935e5c-0000-7000-8000-000000000000/log-sources', ['containerName' => 'shop']);

        self::assertResponseStatusCodeSame(404);
    }

    #[TestWith(['/log-sources'])]
    #[TestWith(['/logs'])]
    public function test_reading_requires_authentication(string $path): void
    {
        $this->client->request('GET', '/api/services/01935e5c-0000-7000-8000-000000000000'.$path);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_lists_log_sources_of_the_service_in_the_order_they_were_added(): void
    {
        $this->logIn();
        $shop = new Service(Uuid::v7(), 'shop');
        $blog = new Service(Uuid::v7(), 'blog');
        $app = new LogSource(Uuid::v7(), $shop, 'shop-app', self::tokenHash(), 'vps-1');
        $worker = new LogSource(Uuid::v7(), $shop, 'shop-worker', self::tokenHash());
        $this->persist($shop, $blog, $app, $worker, new LogSource(Uuid::v7(), $blog, 'blog', self::tokenHash()));

        $this->client->request('GET', "/api/services/{$shop->getId()->toRfc4122()}/log-sources");

        self::assertResponseIsSuccessful();
        self::assertSame([
            [
                'id' => $app->getId()->toRfc4122(),
                'serviceId' => $shop->getId()->toRfc4122(),
                'containerName' => 'shop-app',
                'host' => 'vps-1',
                'lastLogAt' => null,
            ],
            [
                'id' => $worker->getId()->toRfc4122(),
                'serviceId' => $shop->getId()->toRfc4122(),
                'containerName' => 'shop-worker',
                'host' => null,
                'lastLogAt' => null,
            ],
        ], $this->json());
    }

    public function test_returns_newest_lines_of_all_service_containers_oldest_first(): void
    {
        $this->logIn();
        $shop = new Service(Uuid::v7(), 'shop');
        $blog = new Service(Uuid::v7(), 'blog');
        $app = new LogSource(Uuid::v7(), $shop, 'shop-app', self::tokenHash());
        $worker = new LogSource(Uuid::v7(), $shop, 'shop-worker', self::tokenHash());
        $other = new LogSource(Uuid::v7(), $blog, 'blog', self::tokenHash());
        $this->persist($shop, $blog, $app, $worker, $other);
        $this->insertLines(
            [$app, '10:00:01', 'stdout', 'oldest, cut by the limit'],
            [$worker, '10:00:03', 'stderr', 'third'],
            [$app, '10:00:02', 'stdout', 'second'],
            [$other, '10:00:04', 'stdout', 'another service'],
            [$app, '10:00:05', 'stdout', 'newest'],
        );

        $this->client->request('GET', "/api/services/{$shop->getId()->toRfc4122()}/logs?limit=3");

        self::assertResponseIsSuccessful();
        $lines = $this->json();
        self::assertSame(
            [['shop-app', 'stdout', 'second'], ['shop-worker', 'stderr', 'third'], ['shop-app', 'stdout', 'newest']],
            array_map(static fn (array $l) => [$l['container'], $l['stream'], $l['message']], $lines),
        );
        self::assertSame(['id', 'sourceId', 'container', 'loggedAt', 'stream', 'message'], array_keys($lines[0]));
        self::assertSame(
            [$app->getId()->toRfc4122(), $worker->getId()->toRfc4122()],
            [$lines[0]['sourceId'], $lines[1]['sourceId']],
        );
        self::assertIsInt($lines[0]['id']);
        self::assertSame('2026-11-26T10:00:02+00:00', $lines[0]['loggedAt']);
    }

    #[TestWith(['0'])]
    #[TestWith(['1001'])]
    #[TestWith(['many'])]
    public function test_rejects_invalid_limit(string $limit): void
    {
        $this->logIn();
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);

        $this->client->request('GET', "/api/services/{$service->getId()->toRfc4122()}/logs?limit={$limit}");

        self::assertResponseStatusCodeSame(422);
    }

    public function test_logs_of_unknown_service_are_not_found(): void
    {
        $this->logIn();

        $this->client->request('GET', '/api/services/01935e5c-0000-7000-8000-000000000000/logs');

        self::assertResponseStatusCodeSame(404);
    }

    private static function tokenHash(): string
    {
        return IngestToken::generate()->hash();
    }

    /** @param array{LogSource, string, string, string} ...$lines source, time on 2026-11-26, stream, message */
    private function insertLines(array ...$lines): void
    {
        /** @var LogLineRepository $repository */
        $repository = $this->entityManager->getRepository(LogLine::class);
        $repository->insertIgnoringDuplicates(array_map(static fn (array $l) => [
            'log_source_id' => $l[0]->getId()->toRfc4122(),
            'docker_id' => 'abc',
            'logged_at' => "2026-11-26 {$l[1]}",
            'stream' => $l[2],
            'message' => $l[3],
            'event_hash' => hash('sha256', $l[1].$l[3]),
        ], $lines));
    }
}
