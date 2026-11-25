<?php

namespace LatitudeInnovation\Raraxuan\Chat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\Exceptions\InvalidConfigurationException;
use LatitudeInnovation\Raraxuan\Exceptions\MissingApiKeyException;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanApiException;

/**
 * Thin client for the Raraxuan Chat API. Every call is made on behalf of one end
 * user of your app; Raraxuan keeps each end user's conversations separate.
 */
class ChatApi
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function conversations(string $endUser, int $limit = 20): array
    {
        return $this->call($endUser, 'get', 'conversations', ['limit' => $limit])['conversations'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function createConversation(string $endUser, ?string $template = null, array $variables = [], ?string $title = null): array
    {
        $payload = array_filter([
            'template' => $template,
            'variables' => $variables === [] ? null : $variables,
            'title' => $title,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->call($endUser, 'post', 'conversations', (object) $payload)['conversation'];
    }

    /**
     * @return array<string, mixed>
     */
    public function conversation(string $endUser, string $conversationId): array
    {
        return $this->call($endUser, 'get', 'conversations/'.rawurlencode($conversationId))['conversation'];
    }

    public function deleteConversation(string $endUser, string $conversationId): void
    {
        $this->call($endUser, 'delete', 'conversations/'.rawurlencode($conversationId));
    }

    /**
     * Send a message and wait for the whole reply.
     *
     * @return array<string, mixed> { request_id, conversation_id, user_message, message, provider, model, usage, cost }
     */
    public function sendMessage(string $endUser, string $conversationId, string $message): array
    {
        return $this->call($endUser, 'post', 'conversations/'.rawurlencode($conversationId).'/messages', ['message' => $message]);
    }

    /**
     * Send a message and get the raw Server-Sent Events response, unread.
     * A non-2xx response (or a JSON body) means the request was refused.
     */
    public function streamMessage(string $endUser, string $conversationId, string $message): Response
    {
        return $this->stream($endUser, 'conversations/'.rawurlencode($conversationId).'/messages', ['message' => $message]);
    }

    /**
     * Approve or deny an action the assistant asked to run, and wait for the follow-up reply.
     *
     * @return array<string, mixed>
     */
    public function decideToolCall(string $endUser, string $conversationId, string $toolCallId, bool $approve): array
    {
        return $this->call($endUser, 'post', $this->toolCallPath($conversationId, $toolCallId), ['decision' => $approve ? 'approve' : 'deny']);
    }

    /**
     * Approve or deny, getting the raw Server-Sent Events response, unread.
     */
    public function streamToolCallDecision(string $endUser, string $conversationId, string $toolCallId, bool $approve): Response
    {
        return $this->stream($endUser, $this->toolCallPath($conversationId, $toolCallId), ['decision' => $approve ? 'approve' : 'deny']);
    }

    protected function toolCallPath(string $conversationId, string $toolCallId): string
    {
        return 'conversations/'.rawurlencode($conversationId).'/tool-calls/'.rawurlencode($toolCallId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function stream(string $endUser, string $path, array $payload): Response
    {
        try {
            return $this->request($endUser)
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->withOptions(['stream' => true])
                ->post($this->url($path), [...$payload, 'stream' => true]);
        } catch (ConnectionException $exception) {
            throw RaraxuanApiException::connectionFailed($exception);
        }
    }

    protected function request(string $endUser): PendingRequest
    {
        $apiKey = config('raraxuan.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw MissingApiKeyException::missing();
        }

        return Http::withToken(trim($apiKey))
            ->withHeaders(['X-Raraxuan-End-User' => $endUser])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(max(1, (int) config('raraxuan.chat.timeout', 120)));
    }

    /**
     * @param  array<string, mixed>|object  $payload
     * @return array<string, mixed>
     */
    protected function call(string $endUser, string $method, string $path, array|object $payload = []): array
    {
        try {
            $response = $this->request($endUser)->{$method}($this->url($path), $payload);
        } catch (ConnectionException $exception) {
            throw RaraxuanApiException::connectionFailed($exception);
        }

        if (! $response->successful() || $response->json('success') === false) {
            throw RaraxuanApiException::fromResponse($response);
        }

        // Anything else (an empty page, HTML from a proxy, a wrong RARAXUAN_API_URL) is not the Raraxuan API.
        if ($response->json('success') !== true) {
            throw new RaraxuanApiException('Unexpected response from the Raraxuan API. Check RARAXUAN_API_URL.', 'unexpected_response', $response->status());
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    protected function messagesUrl(string $conversationId): string
    {
        return $this->url('conversations/'.rawurlencode($conversationId).'/messages');
    }

    protected function url(string $path): string
    {
        $baseUrl = config('raraxuan.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw InvalidConfigurationException::missingBaseUrl();
        }

        return rtrim($baseUrl, '/').'/'.trim((string) config('raraxuan.chat.api_path', '/api/v1/chat'), '/').'/'.$path;
    }
}
