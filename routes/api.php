<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('test_printer','PrintController@testPrint');

Route::post('print_order','PrintController@printOrder');

Route::post('print_order_with_ip','PrintController@printOrderWithIp');

