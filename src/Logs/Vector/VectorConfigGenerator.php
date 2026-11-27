<?php

declare(strict_types=1);

namespace App\Logs\Vector;

use App\Logs\Dto\VectorConfigResponse;
use App\Logs\Entity\LogSource;
use App\Logs\Security\IngestToken;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes the Vector config file for one log source. One file per source, so Vector started with
 * --config-dir picks each one up on its own. The file holds the source's token, so it is a secret.
 */
final readonly class VectorConfigGenerator
{
    /** Vector's minimum size for a disk buffer. */
    private const int DISK_BUFFER_BYTES = 268435488;

    public function __construct(
        private UrlGeneratorInterface $urls,
        /** Behind a proxy or a tunnel the request's own address is not the one Vector can reach. */
        #[Autowire(env: 'WATCHDECK_PUBLIC_URL')]
        private string $publicUrl = '',
    ) {}

    public function generate(LogSource $source, IngestToken $token): VectorConfigResponse
    {
        $id = $source->getId()->toRfc4122();
        // Component ids are global across all files Vector loads.
        $key = 'watchdeck_'.str_replace('-', '_', $id);
        $containerName = $source->getContainerName();

        $config = [
            'sources' => [
                "{$key}_docker" => [
                    'type' => 'docker_logs',
                    'include_containers' => [$containerName],
                ],
            ],
            'transforms' => [
                // include_containers matches a prefix: "shop" would also take "shop-db".
                "{$key}_exact" => [
                    'type' => 'filter',
                    'inputs' => ["{$key}_docker"],
                    'condition' => '.container_name == '.json_encode($containerName, \JSON_THROW_ON_ERROR),
                ],
            ],
            'sinks' => [
                $key => [
                    'type' => 'http',
                    'inputs' => ["{$key}_exact"],
                    'uri' => $this->ingestUrl(),
                    'method' => 'post',
                    'auth' => ['strategy' => 'bearer', 'token' => $token->value],
                    'encoding' => [
                        'codec' => 'json',
                        'only_fields' => ['container_id', 'container_name', 'message', 'stream', 'timestamp'],
                    ],
                    'framing' => ['method' => 'newline_delimited'],
                    'batch' => ['timeout_secs' => 1],
                    'healthcheck' => ['enabled' => false],
                    'buffer' => ['type' => 'disk', 'max_size' => self::DISK_BUFFER_BYTES],
                ],
            ],
        ];

        return new VectorConfigResponse("watchdeck-{$id}.yaml", Yaml::dump($config, 10, 2));
    }

    private function ingestUrl(): string
    {
        $publicUrl = rtrim(trim($this->publicUrl), '/');
        if ($publicUrl === '') {
            return $this->urls->generate('api_ingest_logs', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $publicUrl.$this->urls->generate('api_ingest_logs');
    }
}
