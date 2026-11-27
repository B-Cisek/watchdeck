<?php

declare(strict_types=1);

namespace App\Tests\Logs\Vector;

use App\Catalog\Entity\Service;
use App\Logs\Entity\LogSource;
use App\Logs\Security\IngestToken;
use App\Logs\Vector\VectorConfigGenerator;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @coversNothing
 */
final class VectorConfigGeneratorTest extends KernelTestCase
{
    #[TestWith(['https://watchdeck.example.com'])]
    #[TestWith(['https://watchdeck.example.com/'])]
    #[TestWith([' https://watchdeck.example.com '])]
    public function test_sends_logs_to_the_public_url_when_set(string $publicUrl): void
    {
        $token = IngestToken::generate();
        $source = new LogSource(Uuid::v7(), new Service(Uuid::v7(), 'shop'), 'shop', $token->hash());
        $generator = new VectorConfigGenerator(self::getContainer()->get(UrlGeneratorInterface::class), $publicUrl);

        $config = Yaml::parse($generator->generate($source, $token)->content);

        self::assertSame(['https://watchdeck.example.com/api/ingest/logs'], array_column($config['sinks'], 'uri'));
    }

    public function test_sends_the_token_as_bearer(): void
    {
        $token = IngestToken::generate();
        $source = new LogSource(Uuid::v7(), new Service(Uuid::v7(), 'shop'), 'shop', $token->hash());

        $config = Yaml::parse(self::getContainer()->get(VectorConfigGenerator::class)->generate($source, $token)->content);

        self::assertSame([['strategy' => 'bearer', 'token' => $token->value]], array_column($config['sinks'], 'auth'));
    }
}
