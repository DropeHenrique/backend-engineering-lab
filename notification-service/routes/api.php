<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::post('/events', [EventController::class, 'store']);
