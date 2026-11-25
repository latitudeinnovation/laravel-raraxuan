<?php

namespace LatitudeInnovation\Raraxuan\Chat;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanException;

/**
 * Decides who the chat user is and which template variables they get.
 * Override from a service provider:
 *
 *     Raraxuan::resolveChatUserUsing(fn (Authenticatable $user) => 'team-'.$user->team_id.':'.$user->id);
 *     Raraxuan::chatVariablesUsing(fn (Authenticatable $user) => ['customer_name' => $user->name]);
 */
class ChatContext
{
    /** @var (Closure(Authenticatable): (string|int|null))|null */
    protected ?Closure $userResolver = null;

    /** @var (Closure(Authenticatable): array<string, mixed>)|null */
    protected ?Closure $variablesResolver = null;

    /** @var (Closure(string): ?Authenticatable)|null */
    protected ?Closure $mcpUserResolver = null;

    public function resolveMcpUserUsing(?Closure $resolver): void
    {
        $this->mcpUserResolver = $resolver;
    }

    /**
     * The user an MCP request from Raraxuan acts for: the reverse of endUserFor().
     * By default the end-user id is the user's auth identifier.
     */
    public function userForEndUser(string $endUserId): ?Authenticatable
    {
        if ($this->mcpUserResolver) {
            return ($this->mcpUserResolver)($endUserId);
        }

        if ($this->userResolver) {
            // A custom id format needs a matching resolveMcpUserUsing(); refuse rather than guess.
            return null;
        }

        $guard = Auth::guard(config('raraxuan.mcp.guard') ?: null);

        return method_exists($guard, 'getProvider') ? $guard->getProvider()?->retrieveById($endUserId) : null;
    }

    public function resolveUserUsing(?Closure $resolver): void
    {
        $this->userResolver = $resolver;
    }

    public function variablesUsing(?Closure $resolver): void
    {
        $this->variablesResolver = $resolver;
    }

    /**
     * The end-user id sent to Raraxuan. By default the user's auth identifier.
     */
    public function endUserFor(Authenticatable $user): string
    {
        $id = $this->userResolver
            ? ($this->userResolver)($user)
            : $user->getAuthIdentifier();

        $id = trim((string) $id);

        if ($id === '') {
            throw new RaraxuanException('Could not determine the chat user id.');
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function variablesFor(?Authenticatable $user): array
    {
        $variables = (array) config('raraxuan.chat.variables', []);

        if ($user && $this->variablesResolver) {
            $variables = array_merge($variables, (array) ($this->variablesResolver)($user));
        }

        return $variables;
    }
}
