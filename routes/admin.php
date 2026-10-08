<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| Mounted with the `web` middleware (see bootstrap/app.php) so the admin shares
| the application's session, then narrowed to the operators named in
| config/admin.php. There is no separate admin guard, because there is no
| separate admin identity: the same signed-in user is either on the allowlist
| or not.
|
*/

Route::middleware(EnsureUserIsAdmin::class)
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
