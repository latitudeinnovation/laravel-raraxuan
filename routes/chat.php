<?php

use Illuminate\Support\Facades\Route;
use LatitudeInnovation\Raraxuan\Http\Controllers\ChatProxyController;

Route::prefix(trim((string) config('raraxuan.chat.route_prefix', 'raraxuan/chat'), '/'))
    ->middleware((array) config('raraxuan.chat.middleware', ['web', 'auth']))
    ->name('raraxuan.chat.')
    ->where(['conversation' => 'conv_[A-Za-z0-9]{1,60}', 'call' => 'call_[A-Za-z0-9]{1,60}'])
    ->group(function (): void {
        Route::get('conversations', [ChatProxyController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ChatProxyController::class, 'store'])->name('conversations.store');
        Route::get('conversations/{conversation}', [ChatProxyController::class, 'show'])->name('conversations.show');
        Route::delete('conversations/{conversation}', [ChatProxyController::class, 'destroy'])->name('conversations.destroy');
        Route::post('conversations/{conversation}/messages', [ChatProxyController::class, 'message'])->name('messages.store');
        Route::post('conversations/{conversation}/tool-calls/{call}', [ChatProxyController::class, 'decide'])->name('tool-calls.decide');
    });
