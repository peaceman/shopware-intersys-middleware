<?php

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

/** @var Router $router */

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\ManufacturerSizeMappingController;
use App\Http\Controllers\SizeMappingExclusionController;
use Illuminate\Routing\Router;

Route::redirect('/', '/login');

// Authentication Routes...
$router->get('login', [LoginController::class, 'showLoginForm'])->name('login');
$router->post('login', [LoginController::class, 'login']);
$router->post('logout', [LoginController::class, 'logout'])->name('logout');

// Registration Routes...
//$router->get('register', 'Auth\RegisterController@showRegistrationForm')->name('register');
//$router->post('register', 'Auth\RegisterController@register');

// Password Reset Routes...
$router->get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
$router->post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
$router->get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
$router->post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::resource('manufacturers', ManufacturerController::class);
    Route::resource('manufacturers.size-mappings', ManufacturerSizeMappingController::class);

    Route::resource('size-mapping-exclusions', SizeMappingExclusionController::class)
        ->only(['index', 'store', 'destroy']);
});

Route::get('/debug-sentry', function () {
    throw new Exception('My first Sentry error!');
});
