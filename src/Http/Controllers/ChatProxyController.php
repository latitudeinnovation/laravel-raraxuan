<?php

namespace LatitudeInnovation\Raraxuan\Http\Controllers;

use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use LatitudeInnovation\Raraxuan\Chat\ChatApi;
use LatitudeInnovation\Raraxuan\Chat\EventStream;
use LatitudeInnovation\Raraxuan\Chat\ChatContext;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanApiException;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The browser-facing side of the chat widget. It runs behind your session
 * middleware, adds your API key on the server and identifies the signed-in user
 * itself: nothing the browser sends decides whose conversations it can reach.
 */
class ChatProxyController extends Controller
{
    public function __construct(
        protected ChatApi $api,
        protected ChatContext $context,
    ) {}

    public function index(Request $request): Response
    {
        return $this->guarded($request, fn (string $endUser): JsonResponse => $this->ok([
            'conversations' => $this->api->conversations($endUser),
        ]));
    }

    public function store(Request $request): Response
    {
        // Template and variables come from your server config, never from the browser.
        return $this->guarded($request, fn (string $endUser): JsonResponse => $this->ok([
            'conversation' => $this->api->createConversation(
                $endUser,
                config('raraxuan.chat.template') ?: null,
                $this->context->variablesFor($request->user()),
            ),
        ], 201));
    }

    public function show(Request $request, string $conversation): Response
    {
        return $this->guarded($request, fn (string $endUser): JsonResponse => $this->ok([
            'conversation' => $this->api->conversation($endUser, $conversation),
        ]));
    }

    public function destroy(Request $request, string $conversation): Response
    {
        return $this->guarded($request, function (string $endUser) use ($conversation): JsonResponse {
            $this->api->deleteConversation($endUser, $conversation);

            return $this->ok(['deleted' => true]);
        });
    }

    public function message(Request $request, string $conversation): Response
    {
        return $this->guarded($request, function (string $endUser) use ($request, $conversation): Response {
            $message = $request->input('message');

            if (! is_string($message) || trim($message) === '' || mb_strlen($message) > 8000) {
                return $this->error('validation_failed', 'Enter a message of up to 8000 characters.', 422);
            }

            $limiterKey = 'raraxuan-chat:'.sha1($endUser);
            $perMinute = max(1, (int) config('raraxuan.chat.messages_per_minute', 20));

            if (RateLimiter::tooManyAttempts($limiterKey, $perMinute)) {
                $seconds = RateLimiter::availableIn($limiterKey);

                return $this->error('rate_limited', "You are sending messages too quickly. Try again in {$seconds} seconds.", 429, ['Retry-After' => (string) $seconds]);
            }

            RateLimiter::hit($limiterKey, 60);

            if (! $request->boolean('stream')) {
                return $this->ok($this->api->sendMessage($endUser, $conversation, $message));
            }

            return $this->relay($this->api->streamMessage($endUser, $conversation, $message));
        });
    }

    /**
     * Approve or deny an action the assistant asked to run. Raraxuan checks the action
     * belongs to this user's conversation and is still waiting for a decision.
     */
    public function decide(Request $request, string $conversation, string $call): Response
    {
        return $this->guarded($request, function (string $endUser) use ($request, $conversation, $call): Response {
            $decision = $request->input('decision');

            if (! in_array($decision, ['approve', 'deny'], true)) {
                return $this->error('validation_failed', 'Choose approve or deny.', 422);
            }

            $approve = $decision === 'approve';

            if (! $request->boolean('stream')) {
                return $this->ok($this->api->decideToolCall($endUser, $conversation, $call, $approve));
            }

            return $this->relay($this->api->streamToolCallDecision($endUser, $conversation, $call, $approve));
        });
    }

    /**
     * Pass Raraxuan's event stream to the browser as it arrives.
     */
    protected function relay(ClientResponse $upstream): Response
    {
        if (! $upstream->successful() || ! str_contains((string) $upstream->header('Content-Type'), 'text/event-stream')) {
            throw RaraxuanApiException::fromResponse($upstream);
        }

        $body = $upstream->toPsrResponse()->getBody();

        return new StreamedResponse(function () use ($body): void {
            // Re-emit each event so error details are filtered like JSON errors are.
            foreach (EventStream::read($body) as [$event, $data]) {
                if ($event === 'error') {
                    [$code, $text, $status] = $this->visitorError(
                        (string) ($data['code'] ?? 'stream_error'),
                        (string) ($data['message'] ?? ''),
                        (int) ($data['status'] ?? 502),
                    );
                    $data = ['code' => $code, 'message' => $text, 'status' => $status];
                }

                echo 'event: '.$event."\n".'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();

                if (connection_aborted()) {
                    break;
                }
            }

            $body->close();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  callable(string): Response  $action
     */
    protected function guarded(Request $request, callable $action): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('unauthenticated', 'Sign in to use the chat.', 401);
        }

        $ability = config('raraxuan.chat.gate');

        if (is_string($ability) && $ability !== '' && Gate::forUser($user)->denies($ability)) {
            return $this->error('forbidden', 'You do not have access to the chat.', 403);
        }

        try {
            return $action($this->context->endUserFor($user));
        } catch (RaraxuanApiException $exception) {
            return $this->fromApiException($exception);
        } catch (RaraxuanException $exception) {
            // Missing API key or base URL: a setup problem, not something the visitor can fix.
            Log::warning('Raraxuan chat is misconfigured: '.$exception->getMessage());

            return $this->unavailable();
        }
    }

    protected function fromApiException(RaraxuanApiException $exception): JsonResponse
    {
        [$code, $message, $status] = $this->visitorError($exception->errorCode, $exception->getMessage(), $exception->status);

        return $this->error($code, $message, $status);
    }

    /**
     * What the visitor sees for an API error. Errors they can act on (not found, invalid
     * input, too many messages, budget reached) pass through. Provider and setup errors
     * become generic messages, so API keys or provider details never reach the browser.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    protected function visitorError(string $code, string $message, int $status): array
    {
        if (in_array($status, [404, 409, 422, 429], true) && $code !== 'end_user_required') {
            return [$code, $message, $status];
        }

        // Never log message content, only what went wrong.
        Log::warning('Raraxuan chat request failed.', ['code' => $code, 'status' => $status, 'error' => $message]);

        if ($code === 'ai_request_failed') {
            return [$code, 'The assistant could not reply right now. Please try again.', 502];
        }

        return ['chat_unavailable', 'The chat is temporarily unavailable. Please try again later.', 503];
    }

    protected function unavailable(): JsonResponse
    {
        return $this->error('chat_unavailable', 'The chat is temporarily unavailable. Please try again later.', 503);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ok(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(['success' => true, 'data' => $data], $status);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function error(string $code, string $message, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse(['success' => false, 'error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }
}
