<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Chat endpoints
Route::post('/init', [ChatController::class, 'init']);
Route::post('/chats', [ChatController::class, 'sendMessage']);
Route::get('/chats', [ChatController::class, 'getChats']);
Route::delete('/chats', [ChatController::class, 'clearChats']);
