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

use Illuminate\Routing\Router;

Route::redirect('/', '/login');

// Authentication Routes...
$router->get('login', 'Auth\LoginController@showLoginForm')->name('login');
$router->post('login', 'Auth\LoginController@login');
$router->post('logout', 'Auth\LoginController@logout')->name('logout');

// Registration Routes...
//$router->get('register', 'Auth\RegisterController@showRegistrationForm')->name('register');
//$router->post('register', 'Auth\RegisterController@register');

// Password Reset Routes...
$router->get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
$router->post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
$router->get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
$router->post('password/reset', 'Auth\ResetPasswordController@reset')->name('password.update');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/home', 'HomeController@index')->name('home');

    Route::resource('manufacturers', 'ManufacturerController');
    Route::resource('manufacturers.size-mappings', 'ManufacturerSizeMappingController');
});

