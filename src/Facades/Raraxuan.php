<?php

namespace LatitudeInnovation\Raraxuan\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array run(array $payload)
 * @method static array processPrompt(string $template, array $variables = [])
 * @method static array ping()
 * @method static array agent(string $agent, array $input = [])
 * @method static \LatitudeInnovation\Raraxuan\Chat\PendingChat chat()
 * @method static void resolveChatUserUsing(\Closure $resolver)
 * @method static void chatVariablesUsing(\Closure $resolver)
 * @method static void resolveMcpUserUsing(\Closure $resolver)
 *
 * @see \LatitudeInnovation\Raraxuan\RaraxuanClient
 */
class Raraxuan extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'raraxuan';
    }
}
