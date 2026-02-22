<?php

use App\Http\Controllers\EmailInboundController;
use App\Http\Controllers\WebhookRoutineController;
use Illuminate\Support\Facades\Route;

Route::post('/email/inbound', EmailInboundController::class);
Route::post('/routines/webhook/{token}', WebhookRoutineController::class);
