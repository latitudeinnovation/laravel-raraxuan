<?php

namespace LatitudeInnovation\Raraxuan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use LatitudeInnovation\Raraxuan\Console\Commands\Concerns\FormatsRaraxuanResponses;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

class PingRaraxuan extends Command
{
    use FormatsRaraxuanResponses;

    protected $signature = 'raraxuan:ping';

    protected $description = 'Check Raraxuan API health.';

    public function handle(): int
    {
        try {
            $response = Raraxuan::ping();
        } catch (RequestException $exception) {
            return $this->renderRequestException($exception);
        }

        $this->line($this->toJson($response));

        return self::SUCCESS;
    }
}
