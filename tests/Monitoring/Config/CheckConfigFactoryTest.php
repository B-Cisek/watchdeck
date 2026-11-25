<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Config;

use App\Monitoring\Config\CheckConfigFactory;
use App\Monitoring\Config\HttpCheckConfig;
use App\Monitoring\Config\TcpCheckConfig;
use App\Monitoring\Enum\CheckType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * @internal
 *
 * @coversNothing
 */
final class CheckConfigFactoryTest extends KernelTestCase
{
    private CheckConfigFactory $factory;

    protected function setUp(): void
    {
        $this->factory = self::getContainer()->get(CheckConfigFactory::class);
    }

    public function test_http_config_uses_defaults(): void
    {
        $config = $this->factory->create(CheckType::HTTP, ['url' => 'https://shop.com/health']);

        self::assertEquals(new HttpCheckConfig('https://shop.com/health', 'GET', 200), $config);
    }

    public function test_http_config_accepts_internal_host_and_custom_values(): void
    {
        $config = $this->factory->create(CheckType::HTTP, [
            'url' => 'http://shop-app:8080/health',
            'method' => 'HEAD',
            'expectedStatus' => 204,
        ]);

        self::assertEquals(new HttpCheckConfig('http://shop-app:8080/health', 'HEAD', 204), $config);
    }

    public function test_tcp_config(): void
    {
        $config = $this->factory->create(CheckType::TCP, ['host' => 'shop-db', 'port' => 5432]);

        self::assertEquals(new TcpCheckConfig('shop-db', 5432), $config);
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidValues')]
    public function test_invalid_value_is_reported_on_its_field(CheckType $type, array $data, string $field): void
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

    /** @return iterable<string, array{CheckType, array<string, mixed>, string}> */
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
    public function test_malformed_data_is_rejected(CheckType $type, array $data, string $exception): void
    {
        $this->expectException($exception);

        $this->factory->create($type, $data);
    }

    /** @return iterable<string, array{CheckType, array<string, mixed>, class-string<\Throwable>}> */
    public static function malformedData(): iterable
    {
        yield 'missing url' => [CheckType::HTTP, [], MissingConstructorArgumentsException::class];

        yield 'missing port' => [CheckType::TCP, ['host' => 'shop-db'], MissingConstructorArgumentsException::class];

        yield 'typo in field name' => [CheckType::HTTP, ['url' => 'https://shop.com', 'expectedStatuss' => 200], ExtraAttributesException::class];

        yield 'field of another type' => [CheckType::TCP, ['host' => 'shop-db', 'port' => 5432, 'url' => 'https://shop.com'], ExtraAttributesException::class];

        yield 'status as string' => [CheckType::HTTP, ['url' => 'https://shop.com', 'expectedStatus' => '200'], NotNormalizableValueException::class];
    }
}
