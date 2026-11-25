<?php

declare(strict_types=1);

namespace App;

use App\Enum\CheckType;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CheckConfigFactory
{
    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(CheckType $type, array $data): object
    {
        $config = $this->denormalizer->denormalize($data, $type->configClass(), context: [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false, // wyłapuje literówki w polach
        ]);

        $violations = $this->validator->validate($config);

        if (count($violations) > 0) {
            throw new ValidationFailedException($config, $violations);
        }

        return $config;
    }
}
