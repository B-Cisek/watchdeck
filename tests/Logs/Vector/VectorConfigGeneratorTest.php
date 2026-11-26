<?php

declare(strict_types=1);

namespace App\Tests\Logs\Vector;

use App\Catalog\Entity\Service;
use App\Logs\Entity\LogSource;
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
        $source = new LogSource(Uuid::v7(), new Service(Uuid::v7(), 'shop'), 'shop');
        $generator = new VectorConfigGenerator(self::getContainer()->get(UrlGeneratorInterface::class), $publicUrl);

        $config = Yaml::parse($generator->generate($source)->content);

        self::assertSame(
            ["https://watchdeck.example.com/api/ingest/logs/{$source->getId()->toRfc4122()}"],
            array_column($config['sinks'], 'uri'),
        );
    }
}
