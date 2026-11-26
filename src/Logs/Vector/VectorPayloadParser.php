<?php

declare(strict_types=1);

namespace App\Logs\Vector;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Reads the body Vector's http sink sends. One bad event is skipped, not the whole batch:
 * Vector does not retry a 4xx, so rejecting the batch would lose every good line in it.
 */
final readonly class VectorPayloadParser
{
    public function __construct(
        private DecoderInterface $decoder,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return list<VectorLogEvent>
     *
     * @throws BadRequestHttpException when nothing in the body can be read
     */
    public function parse(string $body): array
    {
        $rows = $this->decode($body);

        $events = [];
        foreach ($rows as $i => $row) {
            if (!\is_array($row)) {
                $this->skip($i, 'Expected a JSON object.');

                continue;
            }

            try {
                $event = $this->denormalizer->denormalize($row, VectorLogEvent::class);
            } catch (SerializerException $e) {
                $this->skip($i, $e->getMessage());

                continue;
            }

            $violations = $this->validator->validate($event);
            if (\count($violations) > 0) {
                $this->skip($i, (string) $violations);

                continue;
            }

            $events[] = $event;
        }

        return $events;
    }

    /**
     * The generated config sends one JSON object per line. A JSON array is accepted too,
     * as that is what the sink sends when the framing is left out.
     *
     * @return array<int, mixed>
     */
    private function decode(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        if (str_starts_with($body, '[')) {
            try {
                $rows = $this->decoder->decode($body, 'json');
            } catch (SerializerException) {
                throw new BadRequestHttpException('The body is not valid JSON.');
            }

            return \is_array($rows) && array_is_list($rows) ? $rows : throw new BadRequestHttpException('Expected a list of events.');
        }

        $rows = [];
        $unreadable = 0;
        foreach (preg_split('/\R/', $body) ?: [] as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $rows[$i] = $this->decoder->decode($line, 'json');
            } catch (SerializerException $e) {
                ++$unreadable;
                $this->skip($i, $e->getMessage());
            }
        }

        if ($rows === [] && $unreadable > 0) {
            throw new BadRequestHttpException('No line of the body is valid JSON.');
        }

        return $rows;
    }

    private function skip(int $index, string $reason): void
    {
        $this->logger->warning('Skipped log event {index} from Vector: {reason}', ['index' => $index, 'reason' => $reason]);
    }
}
