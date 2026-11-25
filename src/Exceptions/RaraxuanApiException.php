<?php

namespace LatitudeInnovation\Raraxuan\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * An error answered by the Raraxuan API ({ success: false, error: { code, message } }),
 * or a failure to reach it (status 0, code "connection_failed").
 */
class RaraxuanApiException extends RaraxuanException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $code = $response->json('error.code');
        $message = $response->json('error.message') ?? $response->json('message');

        return new self(
            is_string($message) && $message !== '' ? $message : "Raraxuan API request failed (HTTP {$response->status()}).",
            is_string($code) && $code !== '' ? $code : 'http_'.$response->status(),
            $response->status(),
        );
    }

    public static function connectionFailed(Throwable $previous): self
    {
        return new self('Could not reach the Raraxuan API.', 'connection_failed', 0, $previous);
    }
}
