<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanApiException;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

class ChatTest extends TestCase
{
    private const API = 'https://ai.raraxuan.test/api/v1/chat';

    private const SSE = "event: message.started\ndata: {\"conversation_id\":\"conv_abc\"}\n\n"
        ."event: message.delta\ndata: {\"delta\":\"Hel\"}\n\n"
        ."event: message.delta\ndata: {\"delta\":\"lo\"}\n\n"
        ."event: message.completed\ndata: {\"conversation_id\":\"conv_abc\",\"message\":{\"role\":\"assistant\",\"content\":\"Hello\"}}\n\n";

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('raraxuan.api_key', 'rx_secret_key');
        $app['config']->set('raraxuan.base_url', 'https://ai.raraxuan.test');
        $app['config']->set('raraxuan.chat.enabled', true);
        $app['config']->set('raraxuan.chat.template', 'support-bot');
        $app['config']->set('raraxuan.chat.variables', ['company' => 'Acme']);
    }

    public function test_guests_cannot_use_the_chat_routes(): void
    {
        $this->getJson('/raraxuan/chat/conversations')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_listing_sends_the_api_key_and_the_signed_in_user(): void
    {
        Http::fake([self::API.'/conversations*' => Http::response(['success' => true, 'data' => ['conversations' => [['id' => 'conv_abc']]]])]);

        $this->actingAs($this->user(7))
            ->getJson('/raraxuan/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.id', 'conv_abc');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer rx_secret_key')
            && $request->hasHeader('X-Raraxuan-End-User', '7'));
    }

    public function test_new_conversations_use_server_side_template_and_variables_only(): void
    {
        Raraxuan::chatVariablesUsing(fn ($user): array => ['customer' => $user->name]);
        Http::fake([self::API.'/conversations' => Http::response(['success' => true, 'data' => ['conversation' => ['id' => 'conv_abc']]], 201)]);

        $this->actingAs($this->user(7))
            ->postJson('/raraxuan/chat/conversations', ['template' => 'admin-bot', 'variables' => ['company' => 'Evil']])
            ->assertCreated()
            ->assertJsonPath('data.conversation.id', 'conv_abc');

        Http::assertSent(fn (Request $request): bool => $request['template'] === 'support-bot'
            && $request['variables'] === ['company' => 'Acme', 'customer' => 'Ana']);
    }

    public function test_the_browser_cannot_choose_the_end_user(): void
    {
        Http::fake([self::API.'/*' => Http::response(['success' => true, 'data' => ['conversation' => ['id' => 'conv_abc', 'messages' => []]]])]);

        $this->actingAs($this->user(7))
            ->getJson('/raraxuan/chat/conversations/conv_abc', ['X-Raraxuan-End-User' => '1'])
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->header('X-Raraxuan-End-User') === ['7']);
    }

    public function test_custom_user_resolver(): void
    {
        Raraxuan::resolveChatUserUsing(fn ($user): string => 'tenant-1:'.$user->id);
        Http::fake([self::API.'/*' => Http::response(['success' => true, 'data' => ['conversations' => []]])]);

        $this->actingAs($this->user(7))->getJson('/raraxuan/chat/conversations')->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Raraxuan-End-User', 'tenant-1:7'));
    }

    public function test_message_without_streaming_returns_the_reply(): void
    {
        Http::fake([self::API.'/conversations/conv_abc/messages' => Http::response(['success' => true, 'data' => ['message' => ['content' => 'Hello']]])]);

        $this->actingAs($this->user(7))
            ->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('data.message.content', 'Hello');

        Http::assertSent(fn (Request $request): bool => $request['message'] === 'Hi');
    }

    public function test_streaming_message_relays_server_sent_events(): void
    {
        Http::fake([self::API.'/conversations/conv_abc/messages' => Http::response(self::SSE, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->actingAs($this->user(7))
            ->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => 'Hi', 'stream' => true]);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertSame(self::SSE, $response->streamedContent());

        Http::assertSent(fn (Request $request): bool => $request['stream'] === true && $request->hasHeader('Accept', 'text/event-stream'));
    }

    public function test_provider_error_details_are_hidden_from_visitors(): void
    {
        Http::fake([
            self::API.'/conversations/conv_json/messages' => Http::response(['success' => false, 'error' => ['code' => 'ai_request_failed', 'message' => 'API key not valid.']], 502),
            self::API.'/conversations/conv_sse/messages' => Http::response("event: message.started\ndata: {}\n\nevent: error\ndata: {\"code\":\"ai_request_failed\",\"message\":\"API key not valid.\",\"status\":502}\n\n", 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($this->user(7));

        $this->postJson('/raraxuan/chat/conversations/conv_json/messages', ['message' => 'Hi'])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'ai_request_failed')
            ->assertJsonPath('error.message', 'The assistant could not reply right now. Please try again.');

        $body = $this->postJson('/raraxuan/chat/conversations/conv_sse/messages', ['message' => 'Hi', 'stream' => true])->streamedContent();
        $this->assertStringContainsString("event: error\n", $body);
        $this->assertStringContainsString('The assistant could not reply right now.', $body);
        $this->assertStringNotContainsString('API key not valid', $body);
    }

    public function test_visitor_errors_pass_through_and_setup_errors_are_hidden(): void
    {
        Http::fake([
            self::API.'/conversations/conv_gone' => Http::response(['success' => false, 'error' => ['code' => 'conversation_not_found', 'message' => 'Conversation was not found.']], 404),
            self::API.'/conversations/conv_key' => Http::response(['success' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'A valid API key is required.']], 401),
        ]);

        $this->actingAs($this->user(7));

        $this->getJson('/raraxuan/chat/conversations/conv_gone')->assertNotFound()->assertJsonPath('error.code', 'conversation_not_found');

        // A bad API key is the site owner's problem; the visitor sees a generic message.
        $this->getJson('/raraxuan/chat/conversations/conv_key')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'chat_unavailable')
            ->assertJsonMissing(['message' => 'A valid API key is required.']);
    }

    public function test_a_response_that_is_not_the_raraxuan_api_is_reported_as_unavailable(): void
    {
        Http::fake([self::API.'/*' => Http::response('', 200)]);

        $this->actingAs($this->user(7))->postJson('/raraxuan/chat/conversations')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'chat_unavailable');
    }

    public function test_missing_api_key_is_reported_as_unavailable(): void
    {
        config(['raraxuan.api_key' => null]);

        $this->actingAs($this->user(7))->getJson('/raraxuan/chat/conversations')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'chat_unavailable');
    }

    public function test_gate_controls_access(): void
    {
        config(['raraxuan.chat.gate' => 'use-chat']);
        Gate::define('use-chat', fn ($user): bool => $user->id === 1);

        $this->actingAs($this->user(7))->getJson('/raraxuan/chat/conversations')->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_messages_are_rate_limited_per_user(): void
    {
        config(['raraxuan.chat.messages_per_minute' => 2]);
        Http::fake([self::API.'/*' => Http::response(['success' => true, 'data' => ['message' => ['content' => 'ok']]])]);

        $this->actingAs($this->user(7));
        $this->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => 'one'])->assertOk();
        $this->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => 'two'])->assertOk();
        $this->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => 'three'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');

        // Another user has their own allowance.
        $this->actingAs($this->user(8))->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => 'one'])->assertOk();
    }

    public function test_invalid_input_is_rejected_before_calling_raraxuan(): void
    {
        $this->actingAs($this->user(7));

        $this->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => ''])->assertStatus(422);
        $this->postJson('/raraxuan/chat/conversations/conv_abc/messages', ['message' => str_repeat('a', 8001)])->assertStatus(422);
        $this->getJson('/raraxuan/chat/conversations/not-an-id')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_chat_routes_use_the_web_middleware_for_session_and_csrf(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('raraxuan.chat.messages.store');

        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_widget_component_renders_once_for_signed_in_users(): void
    {
        $this->actingAs($this->user(7));

        $html = Blade::render('<x-raraxuan::chat title="Help" theme="dark" /><x-raraxuan::chat />');

        $this->assertSame(1, substr_count($html, '<script src="https://ai.raraxuan.com/chat/v1/raraxuan-chat.js" defer></script>'));
        $this->assertStringContainsString('chat-title="Help"', $html);
        $this->assertStringContainsString('theme="dark"', $html);
        $this->assertStringContainsString('endpoint="http://localhost/raraxuan/chat"', $html);
        $this->assertStringContainsString('csrf-token="', $html);
        $this->assertStringNotContainsString('rx_secret_key', $html);
    }

    public function test_widget_component_renders_nothing_for_guests(): void
    {
        $this->assertSame('', trim(Blade::render('<x-raraxuan::chat />')));
    }

    public function test_sdk_starts_a_conversation_then_sends(): void
    {
        Http::fake([
            self::API.'/conversations' => Http::response(['success' => true, 'data' => ['conversation' => ['id' => 'conv_new']]], 201),
            self::API.'/conversations/conv_new/messages' => Http::response(['success' => true, 'data' => ['conversation_id' => 'conv_new', 'message' => ['content' => 'Hello']]]),
        ]);

        $reply = Raraxuan::chat()->as('worker-1')->message('Hi')->send();

        $this->assertSame('Hello', $reply['message']['content']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/conversations') && $request['template'] === 'support-bot');
    }

    public function test_sdk_continues_a_conversation_and_streams(): void
    {
        Http::fake([self::API.'/conversations/conv_abc/messages' => Http::response(self::SSE, 200, ['Content-Type' => 'text/event-stream'])]);

        $deltas = [];
        $reply = Raraxuan::chat()->as($this->user(7))->conversation('conv_abc')->message('Hi')->stream(function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        });

        $this->assertSame(['Hel', 'lo'], $deltas);
        $this->assertSame('Hello', $reply['message']['content']);
        Http::assertSentCount(1);
    }

    public function test_sdk_throws_structured_errors(): void
    {
        Http::fake([self::API.'/*' => Http::response(['success' => false, 'error' => ['code' => 'budget_exceeded', 'message' => 'Budget reached.']], 429)]);

        try {
            Raraxuan::chat()->as('worker-1')->conversation('conv_abc')->message('Hi')->send();
            $this->fail('Expected an exception.');
        } catch (RaraxuanApiException $exception) {
            $this->assertSame('budget_exceeded', $exception->errorCode);
            $this->assertSame(429, $exception->status);
            $this->assertSame('Budget reached.', $exception->getMessage());
        }
    }

    private function user(int $id): GenericUser
    {
        return new GenericUser(['id' => $id, 'name' => 'Ana', 'remember_token' => null]);
    }
}
