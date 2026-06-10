<?php

namespace LatitudeInnovation\Raraxuan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use JsonException;
use LatitudeInnovation\Raraxuan\Console\Commands\Concerns\FormatsRaraxuanResponses;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

class RunRaraxuanAgent extends Command
{
    use FormatsRaraxuanResponses;

    protected $signature = 'raraxuan:run
        {agent : The Raraxuan agent/template key to run}
        {--input= : JSON object to pass as variables}';

    protected $description = 'Run a Raraxuan agent with a JSON input payload.';

    public function handle(): int
    {
        $variables = $this->variables();

        if ($variables === null) {
            return self::FAILURE;
        }

        try {
            $response = Raraxuan::processPrompt((string) $this->argument('agent'), $variables);
        } catch (RequestException $exception) {
            return $this->renderRequestException($exception);
        }

        $this->line($this->toJson($response));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function variables(): ?array
    {
        $input = $this->option('input');

        if ($input === null || $input === '') {
            return [];
        }

        if (! is_string($input)) {
            $this->error('The --input option must be a JSON object.');

            return null;
        }

        try {
            $decoded = json_decode($input, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('The --input option must be valid JSON: ' . $exception->getMessage());

            return null;
        }

        if (! $decoded instanceof \stdClass) {
            $this->error('The --input option must decode to a JSON object.');

            return null;
        }

        return json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

}
