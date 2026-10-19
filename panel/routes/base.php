<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::get('/branding/{type}', Base\BrandingController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('type', 'logo|favicon')
    ->name('branding');

// Hydra: profile pictures and banners of users.
// Hydra: pages written in the admin area. Public ones work without logging in.
Route::get('/pages/{slug}', Base\PageController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('slug', '[a-z0-9-]+')
    ->name('pages');

// "user" is bound to subusers by the client API, so the parameter has another name.
Route::get('/profiles/{uuid}/{type}', Base\ProfileMediaController::class)
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->where('type', 'avatar|banner')
    ->name('profiles.media');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon)).+');
