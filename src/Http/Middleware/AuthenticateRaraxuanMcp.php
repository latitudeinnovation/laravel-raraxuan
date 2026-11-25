<?php

namespace LatitudeInnovation\Raraxuan\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LatitudeInnovation\Raraxuan\Chat\ChatContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects your MCP server route for Raraxuan chat (alias "raraxuan.mcp").
 *
 * - Rejects requests without the shared secret (RARAXUAN_MCP_TOKEN).
 * - Signs in the user Raraxuan is acting for, so tools can use $request->user()
 *   and your normal policies. Requests without a known user are rejected.
 */
class AuthenticateRaraxuanMcp
{
    public const END_USER_HEADER = 'X-Raraxuan-End-User';

    public function __construct(protected ChatContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = config('raraxuan.mcp.token');

        if (! is_string($token) || $token === '') {
            return $this->deny('MCP access for Raraxuan is not configured.', 503);
        }

        if (! is_string($request->bearerToken()) || ! hash_equals($token, $request->bearerToken())) {
            return $this->deny('Invalid MCP token.', 401);
        }

        $endUser = trim((string) $request->header(self::END_USER_HEADER));

        if ($endUser !== '') {
            $user = $this->context->userForEndUser($endUser);

            if (! $user) {
                return $this->deny('Unknown user.', 403);
            }

            Auth::guard(config('raraxuan.mcp.guard') ?: null)->setUser($user);
            Auth::shouldUse(config('raraxuan.mcp.guard') ?: Auth::getDefaultDriver());
        } elseif (! $this->isDiscovery($request)) {
            // Tool calls always act for a signed-in user of your app.
            return $this->deny('Missing end user.', 403);
        }

        $request->attributes->set('raraxuan_end_user', $endUser !== '' ? $endUser : null);

        return $next($request);
    }

    /**
     * Listing tools (and the handshake) needs no user; calling a tool does.
     */
    protected function isDiscovery(Request $request): bool
    {
        return in_array($request->json('method'), ['initialize', 'notifications/initialized', 'tools/list', 'ping', 'server/discover'], true);
    }

    protected function deny(string $message, int $status): Response
    {
        return response()->json(['error' => $message], $status);
    }
}
