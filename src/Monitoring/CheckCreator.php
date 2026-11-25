<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Catalog\Entity\Service;
use App\Monitoring\Config\CheckConfigFactory;
use App\Monitoring\Dto\CreateCheckRequest;
use App\Monitoring\Entity\Check;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final readonly class CheckCreator
{
    public function __construct(
        private CheckConfigFactory $configFactory,
        private EntityManagerInterface $entityManager,
    ) {}

    public function create(Service $service, CreateCheckRequest $request): Check
    {
        $check = new Check(
            Uuid::v7(),
            $service,
            $request->name,
            $request->type,
            $this->config($request),
            $request->intervalSeconds,
            $request->timeoutSeconds,
        );

        $this->entityManager->persist($check);
        $this->entityManager->flush();

        return $check;
    }

    /** @return array<string, mixed> with the defaults filled in */
    private function config(CreateCheckRequest $request): array
    {
        try {
            return get_object_vars($this->configFactory->create($request->type, $request->config));
        } catch (ValidationFailedException $e) {
            $messages = [];
            foreach ($e->getViolations() as $violation) {
                $messages[$violation->getPropertyPath()] = (string) $violation->getMessage();
            }
        } catch (MissingConstructorArgumentsException $e) {
            $messages = array_fill_keys($e->getMissingConstructorArguments(), 'This value should not be blank.');
        } catch (ExtraAttributesException $e) {
            $messages = array_fill_keys($e->getExtraAttributes(), 'This field was not expected.');
        } catch (NotNormalizableValueException $e) {
            $messages = [
                (string) $e->getPath() => sprintf('This value should be of type %s.', implode('|', $e->getExpectedTypes() ?? ['unknown'])),
            ];
        }

        throw $this->invalidConfig($request, $messages);
    }

    /**
     * Same response shape as the violations reported by #[MapRequestPayload].
     *
     * @param array<array-key, string> $messages by config field
     */
    private function invalidConfig(CreateCheckRequest $request, array $messages): UnprocessableEntityHttpException
    {
        $violations = new ConstraintViolationList();
        foreach ($messages as $field => $message) {
            $violations->add(new ConstraintViolation($message, $message, [], $request, "config.{$field}", $request->config[$field] ?? null));
        }

        return new UnprocessableEntityHttpException('Invalid check config.', new ValidationFailedException($request, $violations));
    }
}
