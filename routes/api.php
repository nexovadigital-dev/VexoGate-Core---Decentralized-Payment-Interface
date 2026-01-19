<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VexoGateController;

/*
|--------------------------------------------------------------------------
| VexoGate Protocol API Routes
|--------------------------------------------------------------------------
|
| API endpoints para el sistema de pagos descentralizado VexoGate
|
*/

Route::prefix('v1')->group(function () {

    // Iniciar proceso de pago
    Route::post('/initiate', [VexoGateController::class, 'initiate'])
        ->name('vexo.initiate');

    // Consultar estado de orden
    Route::get('/order/{id}/status', [VexoGateController::class, 'status'])
        ->name('vexo.status');

    // Webhook para notificaciones de proveedores
    Route::post('/webhook/callback', [VexoGateController::class, 'webhook'])
        ->name('vexo.webhook');

});
