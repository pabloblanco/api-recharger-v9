<?php
/****************************************************************************************************************************
*   2021-2022 GDALab
*****************************************************************************************************************************
* 
*   NOTICE OF LICENSE
*
*
*   DISCLAIMER
*
*
*****************************************************************************************************************************
*
*   @author     GDALab <contact@gdalab.com>
*   @copyright  
*   @license    
*   @web        https://www.gdalab.com/
* 
*****************************************************************************************************************************/

use Illuminate\Support\Facades\Route;

/*
|----------------------------------------------------------------------------------------------------------------------------
| Web Routes
|----------------------------------------------------------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/w', function () {
    return view('welcome');
});

// The environment is local
if (App::environment('local')) {
    
    //Middleware que filtra la solicitud por ips registradas como producción.
    Route::middleware([AllowedIpsInLocalEnvironment::class])->group(function () {

        //Request para verificar estatus del servidor
        Route::get('/echo', [App\Http\Controllers\RechargerController::class, 'echo'])->name('echo');
        //Request para optener token de autenticación
        Route::post('/auth', [App\Http\Controllers\RechargerController::class, 'auth'])->name('auth');
        //Primer paso para una recarga "verificacion de los datos."
        Route::post('/step1', [App\Http\Controllers\RechargerController::class, 'step1'])->name('step1');
        //Segundo paso para una recarga "recarga o activacion del plan."
        Route::post('/step2', [App\Http\Controllers\RechargerController::class, 'step2'])->name('step2');
        //Obtener el saldo de un concentrador
        Route::get('/balance', [App\Http\Controllers\RechargerController::class, 'balance'])->name('balance');

    });     
}else{
    // The environment is in production
    if (App::environment('production')) {
        //Middleware que filtra la solicitud por ips registradas como producción.
        Route::middleware([AllowedIpsInProductionEnvironment::class])->group(function () {

            //Request para verificar estatus del servidor
            Route::get('/echo', [App\Http\Controllers\RechargerController::class, 'echo'])->name('echo');
            //Consulta pago dado un id de mercado pago
            Route::post('/get-payment', [App\Http\Controllers\RechargerController::class, 'getPayment'])->name('get-payment');
            //Request para optener token de autenticación
            Route::post('/auth', [App\Http\Controllers\RechargerController::class, 'auth'])->name('auth');
            //Verifica estado de una recarga
            Route::post('/status-recharge', [App\Http\Controllers\RechargerController::class, 'statusRecharge'])->name('status-recharge');
            //Primer paso para una recarga "verificacion de los datos."
            Route::post('/step1', [App\Http\Controllers\RechargerController::class, 'step1'])->name('step1');
            //Segundo paso para una recarga con comprobación de pago
            Route::post('/verification-pay-step2', [App\Http\Controllers\RechargerController::class, 'verificationPayStep2'])->name('verification-pay-step2');
            //Segundo paso para una recarga "recarga o activacion del plan."
            Route::post('/step2', [App\Http\Controllers\RechargerController::class, 'step2'])->name('step2');
            //Segundo paso para una recarga "recarga o activacion del plan."
            Route::post('/step2-seller', [App\Http\Controllers\RechargerController::class, 'step2Seller'])->name('step2-seller');
            //Obtener el saldo de un concentrador
            Route::get('/balance', [App\Http\Controllers\RechargerController::class, 'balance'])->name('balance');

            Route::get('/do-recharge', [App\Http\Controllers\RechargerController::class, 'doRecharge'])->name('do-recharge');
            //Verifica si el proceso de recarga tiene mas de un tiempo X ejcutandose y lo reinicia
            //Se debe ejecutar desde un cron cada minúto
            Route::get('/reset-recharge-process', [App\Http\Controllers\RechargerController::class, 'resetRechargeRrocess'])->name('reset-recharge-process');
            //Servicio que se ejecuta por cron 1 vez al día y activa recargas de promoción
            Route::get('/active-recharge-prom', [App\Http\Controllers\RechargerController::class, 'activeRechargeProm'])->name('active-recharge-prom');
            //Proceso que se ejecuta por cron, activa servicios "extras" (nav. nocturna) para las recargas
            Route::get('/extra-recharge', [App\Http\Controllers\RechargerController::class, 'extraRecharge'])->name('extra-recharge');
            //Proceso que se ejecuta por cron, activa servicios "extras" (nav. nocturna) para las altas
            Route::get('/extra-register', [App\Http\Controllers\RechargerController::class, 'extraRegister'])->name('extra-register');
            //Request que se debe ejecutar desde un cron cada minuto y envia las notificaciones al slack registradas en la tabla de logs
            Route::get('/send-alert-logs', [App\Http\Controllers\RechargerController::class, 'sendAlertLogs'])->name('send-alert-logs');
            //Request que se debe ejecutar desde un cron una vez al dia preferiblemente a las 23:59
            //elimina los registros de la tabla logs que cumplan con la condición de tiempo
            Route::get('/remove-logs', [App\Http\Controllers\RechargerController::class, 'removeLogs'])->name('remove-logs');
            //Request para ser ejecutado desde un cron, genera archivo de conciliación para bluelabel
            //Va a consultar todas las recargas del dia anterior de 12:00:00 - 23:59:59
            //Debe ejecutarse todos los días a las 02:00
            Route::get('/file-bluelabel', [App\Http\Controllers\RechargerController::class, 'fileBluelabel'])->name('file-bluelabel');
            //Carga masiva de servicios de rentención
            Route::post('/massive-retention/{email}', [App\Http\Controllers\RechargerController::class, 'massiveRetention'])->name('massive-retention');
            //Request para ejecutar desde cron, activa las solicitudes de servicio de rentención
            Route::get('/process-retention', [App\Http\Controllers\RechargerController::class, 'processRetention'])->name('process-retention');

        });   
    } 
    
}