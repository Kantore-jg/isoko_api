<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Market Management API',
        'status' => 'ok',
    ]);
});
