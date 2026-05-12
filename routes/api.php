<?php

use App\Http\Controllers\AiAdvisor\AdvisorController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai-advisor')
    ->middleware(['throttle:ai_advisor'])
    ->group(function () {
        Route::post('/ask', [AdvisorController::class, 'ask']);
    });
