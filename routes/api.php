<?php

use Illuminate\Http\Request;


Route::get('test_printer','PrintController@testPrint');

Route::post('print_order','PrintController@printOrder');

Route::post('print_order_with_ip','PrintController@printOrderWithIp');

