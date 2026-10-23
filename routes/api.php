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

Route::get('bookings', ['as' => 'bookings.index', 'uses' => 'Bookings\BookingsController@index']);
Route::post('bookings', ['as' => 'bookings.store', 'uses' => 'Bookings\BookingsController@store']);
Route::get('bookings/{reserveId}', ['as' => 'bookings.show', 'uses' => 'Bookings\BookingsController@show']);
Route::delete('bookings/{reserveId}', ['as' => 'bookings.destroy', 'uses' => 'Bookings\BookingsController@destroy']);
