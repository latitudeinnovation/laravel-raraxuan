@props([
    'title' => null,
    'welcome' => null,
    'placeholder' => null,
    'theme' => null,
    'position' => null,
    'endpoint' => null,
    'open' => false,
])

@php
    $widget = (array) config('raraxuan.chat.widget', []);
    $gate = config('raraxuan.chat.gate');
    $visible = config('raraxuan.chat.enabled')
        && auth()->check()
        && (! is_string($gate) || $gate === '' || \Illuminate\Support\Facades\Gate::allows($gate));
@endphp

@if ($visible)
    @once
        <script src="{{ config('raraxuan.chat.widget_url') }}" defer></script>
    @endonce

    <raraxuan-chat
        endpoint="{{ $endpoint ?? url(trim((string) config('raraxuan.chat.route_prefix', 'raraxuan/chat'), '/')) }}"
        csrf-token="{{ csrf_token() }}"
        chat-title="{{ $title ?? ($widget['title'] ?? 'Assistant') }}"
        theme="{{ $theme ?? ($widget['theme'] ?? 'light') }}"
        position="{{ $position ?? ($widget['position'] ?? 'bottom-right') }}"
        @if ($welcome ?? ($widget['welcome'] ?? null)) welcome="{{ $welcome ?? $widget['welcome'] }}" @endif
        @if ($placeholder ?? ($widget['placeholder'] ?? null)) placeholder="{{ $placeholder ?? $widget['placeholder'] }}" @endif
        @if ($open) open @endif
        {{ $attributes }}
    ></raraxuan-chat>
@endif
