<?php

use App\Http\Controllers\EmailInboundController;
use Illuminate\Support\Facades\Route;

Route::post('/email/inbound', EmailInboundController::class);
