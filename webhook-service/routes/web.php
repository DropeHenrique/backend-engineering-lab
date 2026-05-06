<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/webhook/logs', [WebhookController::class, 'logs']);

Route::middleware(['webhook.raw'])->group(function () {
    Route::post('/webhook/{provider}', [WebhookController::class, 'receive']);
});
