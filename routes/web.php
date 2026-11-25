<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\ManufacturingPostController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [ManufacturingPostController::class, 'create'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/posts', [ManufacturingPostController::class, 'store'])->name('posts.store');
    Route::get('/posts', [ManufacturingPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/{generation}', [ManufacturingPostController::class, 'show'])->name('posts.show')->whereNumber('generation');
    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'edit'])->name('knowledge.edit');
    Route::put('/knowledge-base', [KnowledgeBaseController::class, 'update'])->name('knowledge.update');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/keys', [SettingsController::class, 'storeKey'])->name('settings.keys.store');
    Route::delete('/settings/keys/{apiKey}', [SettingsController::class, 'destroyKey'])->name('settings.keys.destroy');
    Route::post('/chat/sessions', [ChatController::class, 'storeSession'])->name('chat.sessions.store');
    Route::get('/chat/{conversation?}', [ChatController::class, 'show'])->name('chat.show')->whereNumber('conversation');
    Route::post('/chat/post', [ChatController::class, 'generate'])->name('chat.generate');
    Route::post('/chat/{conversation}/post', [ChatController::class, 'generate'])->name('chat.session.generate')->whereNumber('conversation');
    Route::post('/chat/{conversation}', [ChatController::class, 'store'])->name('chat.session.store')->whereNumber('conversation');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/generations/{generation}', [GenerationController::class, 'show'])->name('generations.show');
});
