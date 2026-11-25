<?php

declare(strict_types=1);

namespace App\Tests;

use App\CheckConfigFactory;
use App\Config\HttpCheckConfig;
use App\Config\TcpCheckConfig;
use App\Enum\CheckType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class CheckConfigFactoryTest extends KernelTestCase
{
    private CheckConfigFactory $factory;

    protected function setUp(): void
    {
        $this->factory = self::getContainer()->get(CheckConfigFactory::class);
    }

    public function testHttpConfigUsesDefaults(): void
    {
        $config = $this->factory->create(CheckType::HTTP, ['url' => 'https://shop.com/health']);

        self::assertEquals(new HttpCheckConfig('https://shop.com/health', 'GET', 200), $config);
    }

    public function testHttpConfigAcceptsInternalHostAndCustomValues(): void
    {
        $config = $this->factory->create(CheckType::HTTP, [
            'url' => 'http://shop-app:8080/health',
            'method' => 'HEAD',
            'expectedStatus' => 204,
        ]);

        self::assertEquals(new HttpCheckConfig('http://shop-app:8080/health', 'HEAD', 204), $config);
    }

    public function testTcpConfig(): void
    {
        $config = $this->factory->create(CheckType::TCP, ['host' => 'shop-db', 'port' => 5432]);

        self::assertEquals(new TcpCheckConfig('shop-db', 5432), $config);
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidValues')]
    public function testInvalidValueIsReportedOnItsField(CheckType $type, array $data, string $field): void
    {
        try {
            $this->factory->create($type, $data);
            self::fail('Expected a validation error.');
        } catch (ValidationFailedException $e) {
            $fields = array_map(
                static fn ($violation) => $violation->getPropertyPath(),
                iterator_to_array($e->getViolations()),
            );

            self::assertContains($field, $fields);
        }
    }

    public static function invalidValues(): iterable
    {
        yield 'empty url' => [CheckType::HTTP, ['url' => ''], 'url'];
        yield 'not a url' => [CheckType::HTTP, ['url' => 'shop'], 'url'];
        yield 'scheme other than http' => [CheckType::HTTP, ['url' => 'ftp://shop.com'], 'url'];
        yield 'unsupported method' => [CheckType::HTTP, ['url' => 'https://shop.com', 'method' => 'DELETE'], 'method'];
        yield 'status below range' => [CheckType::HTTP, ['url' => 'https://shop.com', 'expectedStatus' => 99], 'expectedStatus'];
        yield 'status above range' => [CheckType::HTTP, ['url' => 'https://shop.com', 'expectedStatus' => 600], 'expectedStatus'];
        yield 'empty host' => [CheckType::TCP, ['host' => '', 'port' => 5432], 'host'];
        yield 'port zero' => [CheckType::TCP, ['host' => 'shop-db', 'port' => 0], 'port'];
        yield 'port above range' => [CheckType::TCP, ['host' => 'shop-db', 'port' => 65536], 'port'];
    }

    /**
     * @param array<string, mixed>     $data
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('malformedData')]
    public function testMalformedDataIsRejected(CheckType $type, array $data, string $exception): void
    {
        $this->expectException($exception);

        $this->factory->create($type, $data);
    }

    public static function malformedData(): iterable
    {
        yield 'missing url' => [CheckType::HTTP, [], MissingConstructorArgumentsException::class];
        yield 'missing port' => [CheckType::TCP, ['host' => 'shop-db'], MissingConstructorArgumentsException::class];
        yield 'typo in field name' => [CheckType::HTTP, ['url' => 'https://shop.com', 'expectedStatuss' => 200], ExtraAttributesException::class];
        yield 'field of another type' => [CheckType::TCP, ['host' => 'shop-db', 'port' => 5432, 'url' => 'https://shop.com'], ExtraAttributesException::class];
        yield 'status as string' => [CheckType::HTTP, ['url' => 'https://shop.com', 'expectedStatus' => '200'], NotNormalizableValueException::class];
    }
}
