<?php

namespace LatitudeInnovation\Raraxuan\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;

class ChatDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    public function test_chat_is_off_by_default(): void
    {
        $this->assertFalse(config('raraxuan.chat.enabled'));
        $this->assertNull($this->app['router']->getRoutes()->getByName('raraxuan.chat.conversations.index'));

        $this->actingAs(new GenericUser(['id' => 1]))->getJson('/raraxuan/chat/conversations')->assertNotFound();
        $this->assertSame('', trim(Blade::render('<x-raraxuan::chat />')));
    }
}
