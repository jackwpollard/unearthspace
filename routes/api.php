<?php

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

Route::group(['namespace' => 'Api'], function() {

	Route::get('/satellites', 'SatelliteController@list');

	Route::get('/satellites/{satellite}/passes', 'SatelliteController@passes');

	Route::get('/satellites/{satellite}/position', 'SatelliteController@position');

});