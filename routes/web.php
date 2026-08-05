<?php

use App\Http\Controllers\RelatorioDiaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/relatorio-dia/{modo}/{entidadeId}/{data}', [RelatorioDiaController::class, 'show'])
    ->where(['modo' => 'sala|profissional', 'entidadeId' => '[0-9]+', 'data' => '\d{4}-\d{2}-\d{2}'])
    ->middleware('auth')
    ->name('relatorio-dia');
