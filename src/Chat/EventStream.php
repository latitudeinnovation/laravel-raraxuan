<?php

namespace LatitudeInnovation\Raraxuan\Chat;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Reads Server-Sent Events from a response body.
 */
final class EventStream
{
    /**
     * @return Generator<int, array{0: string, 1: array<string, mixed>}>
     */
    public static function read(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= str_replace(["\r\n", "\r"], "\n", $body->read(8192));

            while (($boundary = strpos($buffer, "\n\n")) !== false) {
                $block = substr($buffer, 0, $boundary);
                $buffer = substr($buffer, $boundary + 2);

                if ($event = self::parse($block)) {
                    yield $event;
                }
            }
        }

        if ($event = self::parse($buffer)) {
            yield $event;
        }
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private static function parse(string $block): ?array
    {
        $event = 'message';
        $data = [];

        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }

        if ($data === []) {
            return null;
        }

        $decoded = json_decode(implode("\n", $data), true);

        return [$event, is_array($decoded) ? $decoded : []];
    }
}
