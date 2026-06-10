<?php

namespace LatitudeInnovation\Raraxuan\Console\Commands\Concerns;

use Illuminate\Http\Client\RequestException;

trait FormatsRaraxuanResponses
{
    /**
     * @param  array<mixed>  $response
     */
    protected function toJson(array $response): string
    {
        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected function renderRequestException(RequestException $exception): int
    {
        $response = $exception->response;

        $this->error('Raraxuan API request failed with HTTP status ' . $response->status() . '.');

        $json = $response->json();

        if (is_array($json)) {
            $this->line($this->toJson($json));
        } else {
            $this->line((string) $response->body());
        }

        return self::FAILURE;
    }
}
