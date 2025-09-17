<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Chat endpoints
Route::post('/init', [ChatController::class, 'init']); // No middleware - public endpoint
Route::get('/rooms', [ChatController::class, 'getUserRooms']); // No middleware - public endpoint

// Protected endpoints that require token authentication
Route::middleware('verify.chat.token')->group(function () {
    Route::post('/chats', [ChatController::class, 'sendMessage']);
    Route::get('/chats', [ChatController::class, 'getChats']);
    Route::delete('/chats', [ChatController::class, 'clearChats']);
});
