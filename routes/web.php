<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/admin/login', [LoginController::class, 'login']);
Route::post('/admin/login', [LoginController::class, 'gglogin']);
Route::get('/', function () {
    return view('welcome');
});
