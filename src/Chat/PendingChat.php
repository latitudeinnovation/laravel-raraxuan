<?php

namespace LatitudeInnovation\Raraxuan\Chat;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanApiException;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanException;

/**
 * Fluent chat builder:
 *
 *     $reply = Raraxuan::chat()->message('Hello')->send();
 *     $reply = Raraxuan::chat()->as($user)->conversation($reply['conversation_id'])->message('And then?')->send();
 */
class PendingChat
{
    protected ?string $endUser = null;

    protected ?Authenticatable $user = null;

    protected ?string $conversationId = null;

    protected ?string $template = null;

    /** @var array<string, mixed>|null */
    protected ?array $variables = null;

    protected ?string $message = null;

    public function __construct(
        protected ChatApi $api,
        protected ChatContext $context,
    ) {}

    /**
     * Chat as this user (default: the signed-in user). A string or int is used as the end-user id as-is.
     */
    public function as(Authenticatable|string|int $user): static
    {
        if ($user instanceof Authenticatable) {
            $this->user = $user;
            $this->endUser = $this->context->endUserFor($user);
        } else {
            $this->endUser = (string) $user;
        }

        return $this;
    }

    public function conversation(?string $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    /**
     * Use this prompt template for a new conversation (default: config raraxuan.chat.template).
     *
     * @param  array<string, mixed>  $variables
     */
    public function template(string $template, array $variables = []): static
    {
        $this->template = $template;
        $this->variables = $variables;

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Send the message and return the reply. Starts a conversation when none is set;
     * the reply's "conversation_id" continues it.
     *
     * @return array<string, mixed>
     */
    public function send(): array
    {
        $message = $this->requireMessage();

        return $this->api->sendMessage($this->endUser(), $this->ensureConversation(), $message);
    }

    /**
     * Send the message and call $onDelta with each piece of the reply as it arrives.
     *
     * @param  callable(string): void  $onDelta
     * @return array<string, mixed> The completed reply, as from send().
     */
    public function stream(callable $onDelta): array
    {
        $message = $this->requireMessage();
        $response = $this->api->streamMessage($this->endUser(), $this->ensureConversation(), $message);

        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'text/event-stream')) {
            throw RaraxuanApiException::fromResponse($response);
        }

        $completed = null;

        foreach (EventStream::read($response->toPsrResponse()->getBody()) as [$event, $data]) {
            match ($event) {
                'message.delta' => $onDelta((string) ($data['delta'] ?? '')),
                'message.completed' => $completed = $data,
                'error' => throw new RaraxuanApiException((string) ($data['message'] ?? 'The reply failed.'), (string) ($data['code'] ?? 'stream_error'), (int) ($data['status'] ?? 502)),
                default => null,
            };
        }

        if ($completed === null) {
            throw new RaraxuanApiException('The reply stream ended early.', 'stream_interrupted', 502);
        }

        return $completed;
    }

    /**
     * Approve an action the assistant asked to run (from a reply's "approvals"). It runs
     * in your application through your MCP server, then the assistant continues.
     *
     * @return array<string, mixed> The follow-up reply.
     */
    public function approve(string $toolCallId): array
    {
        return $this->api->decideToolCall($this->endUser(), $this->requireConversation(), $toolCallId, true);
    }

    /**
     * Deny an action the assistant asked to run. Nothing runs.
     *
     * @return array<string, mixed>
     */
    public function deny(string $toolCallId): array
    {
        return $this->api->decideToolCall($this->endUser(), $this->requireConversation(), $toolCallId, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function conversations(int $limit = 20): array
    {
        return $this->api->conversations($this->endUser(), $limit);
    }

    /**
     * The current conversation with its messages.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->api->conversation($this->endUser(), $this->requireConversation());
    }

    public function delete(): void
    {
        $this->api->deleteConversation($this->endUser(), $this->requireConversation());
    }

    protected function ensureConversation(): string
    {
        if ($this->conversationId === null) {
            $conversation = $this->api->createConversation(
                $this->endUser(),
                $this->template ?? (config('raraxuan.chat.template') ?: null),
                $this->variables ?? $this->context->variablesFor($this->user),
            );

            $this->conversationId = $conversation['id'];
        }

        return $this->conversationId;
    }

    protected function endUser(): string
    {
        if ($this->endUser === null) {
            $user = Auth::user();

            if (! $user) {
                throw new RaraxuanException('No chat user: call ->as($user) or sign a user in.');
            }

            $this->as($user);
        }

        return $this->endUser;
    }

    protected function requireMessage(): string
    {
        if ($this->message === null || trim($this->message) === '') {
            throw new RaraxuanException('Set a message with ->message() before sending.');
        }

        return $this->message;
    }

    protected function requireConversation(): string
    {
        return $this->conversationId ?? throw new RaraxuanException('Set a conversation with ->conversation($id).');
    }
}
