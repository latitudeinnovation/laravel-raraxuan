<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

class ChatApprovalAndMcpTest extends TestCase
{
    private const API = 'https://ai.raraxuan.test/api/v1/chat';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('raraxuan.api_key', 'rx_secret_key');
        $app['config']->set('raraxuan.base_url', 'https://ai.raraxuan.test');
        $app['config']->set('raraxuan.chat.enabled', true);
        $app['config']->set('raraxuan.mcp.token', 'mcp-secret');
    }

    protected function defineRoutes($router): void
    {
        // Stands in for an app's laravel/mcp route.
        $router->post('/mcp/raraxuan', fn (HttpRequest $request) => response()->json([
            'user' => $request->user()?->getAuthIdentifier(),
            'end_user' => $request->attributes->get('raraxuan_end_user'),
        ]))->middleware('raraxuan.mcp');
    }

    public function test_the_widget_can_approve_through_the_proxy(): void
    {
        Http::fake([self::API.'/conversations/conv_abc/tool-calls/call_xyz' => Http::response(['success' => true, 'data' => ['decided_tool_call' => ['status' => 'completed'], 'message' => ['content' => 'Done.']]])]);

        $this->actingAs($this->user(7))
            ->postJson('/raraxuan/chat/conversations/conv_abc/tool-calls/call_xyz', ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.decided_tool_call.status', 'completed');

        Http::assertSent(fn (Request $request): bool => $request['decision'] === 'approve' && $request->hasHeader('X-Raraxuan-End-User', '7'));
    }

    public function test_approval_decisions_stream_and_conflicts_reach_the_visitor(): void
    {
        $sse = "event: tool.started\ndata: {\"tool\":\"cancel_order\"}\n\nevent: message.completed\ndata: {\"message\":{\"content\":\"Done.\"}}\n\n";
        Http::fake([
            self::API.'/conversations/conv_abc/tool-calls/call_ok' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
            self::API.'/conversations/conv_abc/tool-calls/call_old' => Http::response(['success' => false, 'error' => ['code' => 'tool_call_expired', 'message' => 'This approval request has expired.']], 409),
        ]);

        $this->actingAs($this->user(7));

        $body = $this->postJson('/raraxuan/chat/conversations/conv_abc/tool-calls/call_ok', ['decision' => 'approve', 'stream' => true])->streamedContent();
        $this->assertStringContainsString('event: tool.started', $body);
        $this->assertStringContainsString('event: message.completed', $body);

        $this->postJson('/raraxuan/chat/conversations/conv_abc/tool-calls/call_old', ['decision' => 'approve'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'tool_call_expired');
    }

    public function test_invalid_decisions_are_rejected_before_calling_raraxuan(): void
    {
        $this->actingAs($this->user(7));

        $this->postJson('/raraxuan/chat/conversations/conv_abc/tool-calls/call_xyz', ['decision' => 'maybe'])->assertStatus(422);
        $this->postJson('/raraxuan/chat/conversations/conv_abc/tool-calls/not-a-call', ['decision' => 'approve'])->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_sdk_approve_and_deny(): void
    {
        Http::fake([self::API.'/conversations/conv_abc/tool-calls/*' => Http::response(['success' => true, 'data' => ['message' => ['content' => 'ok']]])]);

        Raraxuan::chat()->as('worker-1')->conversation('conv_abc')->approve('call_1');
        Raraxuan::chat()->as('worker-1')->conversation('conv_abc')->deny('call_2');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/call_1') && $request['decision'] === 'approve');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/call_2') && $request['decision'] === 'deny');
    }

    public function test_mcp_route_requires_the_shared_secret(): void
    {
        $this->postJson('/mcp/raraxuan', ['method' => 'tools/list'])->assertUnauthorized();
        $this->postJson('/mcp/raraxuan', ['method' => 'tools/list'], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        config(['raraxuan.mcp.token' => null]);
        $this->postJson('/mcp/raraxuan', ['method' => 'tools/list'], ['Authorization' => 'Bearer mcp-secret'])->assertStatus(503);
    }

    public function test_listing_tools_needs_no_user_but_calling_one_does(): void
    {
        $headers = ['Authorization' => 'Bearer mcp-secret'];

        $this->postJson('/mcp/raraxuan', ['method' => 'tools/list'], $headers)->assertOk()->assertJsonPath('user', null);
        $this->postJson('/mcp/raraxuan', ['method' => 'tools/call'], $headers)->assertForbidden();
    }

    public function test_tool_calls_run_as_the_chatting_user(): void
    {
        $this->fakeUserProvider(['7' => $this->user(7)]);
        $headers = ['Authorization' => 'Bearer mcp-secret', 'X-Raraxuan-End-User' => '7'];

        $this->postJson('/mcp/raraxuan', ['method' => 'tools/call'], $headers)
            ->assertOk()
            ->assertJsonPath('user', 7)
            ->assertJsonPath('end_user', '7');

        $this->postJson('/mcp/raraxuan', ['method' => 'tools/call'], [...$headers, 'X-Raraxuan-End-User' => '999'])->assertForbidden();
    }

    public function test_custom_user_ids_need_a_matching_mcp_resolver(): void
    {
        $this->fakeUserProvider(['7' => $this->user(7)]);
        Raraxuan::resolveChatUserUsing(fn ($user): string => 'tenant-1:'.$user->id);
        $headers = ['Authorization' => 'Bearer mcp-secret', 'X-Raraxuan-End-User' => 'tenant-1:7'];

        // Without a reverse mapping the request is refused rather than guessed.
        $this->postJson('/mcp/raraxuan', ['method' => 'tools/call'], $headers)->assertForbidden();

        Raraxuan::resolveMcpUserUsing(fn (string $id) => $id === 'tenant-1:7' ? $this->user(7) : null);
        $this->postJson('/mcp/raraxuan', ['method' => 'tools/call'], $headers)->assertOk()->assertJsonPath('user', 7);
    }

    /**
     * @param  array<string, GenericUser>  $users
     */
    private function fakeUserProvider(array $users): void
    {
        Auth::provider('fake', fn () => new class($users) implements UserProvider
        {
            public function __construct(private array $users) {}

            public function retrieveById($identifier)
            {
                return $this->users[(string) $identifier] ?? null;
            }

            public function retrieveByToken($identifier, $token) {}

            public function updateRememberToken($user, $token): void {}

            public function retrieveByCredentials(array $credentials) {}

            public function validateCredentials($user, array $credentials): bool
            {
                return false;
            }

            public function rehashPasswordIfRequired($user, array $credentials, bool $force = false): void {}
        });

        config(['auth.providers.users' => ['driver' => 'fake']]);
        Auth::forgetGuards();
    }

    private function user(int $id): GenericUser
    {
        return new GenericUser(['id' => $id, 'name' => 'Ana', 'remember_token' => null]);
    }
}
