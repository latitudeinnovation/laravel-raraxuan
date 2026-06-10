<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class RaraxuanCommandTest extends TestCase
{
    public function test_run_command_processes_an_agent_with_json_input(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        $response = [
            'success' => true,
            'data' => [
                'reply' => 'Hello',
            ],
        ];

        Http::fake([
            'https://ai.raraxuan.test/v1/prompts/process' => Http::response($response, 200),
        ]);

        $this->artisan('raraxuan:run', [
            'agent' => 'customer-support-reply',
            '--input' => '{"customer_message":"Hello"}',
        ])
            ->expectsOutput($this->prettyJson($response))
            ->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://ai.raraxuan.test/v1/prompts/process'
                && $request->hasHeader('Authorization', 'Bearer rx_test_key')
                && $request['template'] === 'customer-support-reply'
                && $request['variables'] === ['customer_message' => 'Hello'];
        });
    }

    public function test_run_command_uses_empty_variables_when_input_is_omitted(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        Http::fake([
            'https://ai.raraxuan.test/v1/prompts/process' => Http::response(['success' => true], 200),
        ]);

        $this->artisan('raraxuan:run', [
            'agent' => 'customer-support-reply',
        ])
            ->expectsOutput($this->prettyJson(['success' => true]))
            ->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            return $request['template'] === 'customer-support-reply'
                && $request['variables'] === [];
        });
    }

    public function test_run_command_fails_when_input_is_invalid_json(): void
    {
        Http::fake();

        $this->artisan('raraxuan:run', [
            'agent' => 'customer-support-reply',
            '--input' => '{invalid',
        ])
            ->expectsOutputToContain('The --input option must be valid JSON:')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_run_command_fails_when_input_is_not_a_json_object(): void
    {
        Http::fake();

        $this->artisan('raraxuan:run', [
            'agent' => 'customer-support-reply',
            '--input' => '["Hello"]',
        ])
            ->expectsOutput('The --input option must decode to a JSON object.')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_run_command_prints_api_error_responses_without_a_stack_trace(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        $response = [
            'message' => 'The variables field is required.',
            'errors' => [
                'variables' => [
                    'The variables field is required.',
                ],
            ],
        ];

        Http::fake([
            'https://ai.raraxuan.test/v1/prompts/process' => Http::response($response, 422),
        ]);

        $this->artisan('raraxuan:run', [
            'agent' => 'json-schema-extractor',
        ])
            ->expectsOutput('Raraxuan API request failed with HTTP status 422.')
            ->expectsOutput($this->prettyJson($response))
            ->assertExitCode(1);
    }

    public function test_ping_command_checks_api_health(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        $response = [
            'success' => true,
            'data' => [
                'status' => 'ok',
            ],
        ];

        Http::fake([
            'https://ai.raraxuan.test/v1/ping' => Http::response($response, 200),
        ]);

        $this->artisan('raraxuan:ping')
            ->expectsOutput($this->prettyJson($response))
            ->assertExitCode(0);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ai.raraxuan.test/v1/ping'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer rx_test_key'));
    }

    public function test_ping_command_prints_api_error_responses_without_a_stack_trace(): void
    {
        config([
            'raraxuan.api_key' => 'rx_test_key',
            'raraxuan.base_url' => 'https://ai.raraxuan.test',
        ]);

        $response = [
            'success' => false,
            'error' => [
                'code' => 'service_unavailable',
                'message' => 'Raraxuan is unavailable.',
            ],
        ];

        Http::fake([
            'https://ai.raraxuan.test/v1/ping' => Http::response($response, 503),
        ]);

        $this->artisan('raraxuan:ping')
            ->expectsOutput('Raraxuan API request failed with HTTP status 503.')
            ->expectsOutput($this->prettyJson($response))
            ->assertExitCode(1);
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function prettyJson(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
