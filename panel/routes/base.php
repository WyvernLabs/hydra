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

// Hydra: the panel as an installable app (addon "pwa").
Route::get('/manifest.webmanifest', [Base\PwaController::class, 'manifest'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('pwa.manifest');
Route::get('/pwa/{type}-{size}.png', [Base\PwaController::class, 'icon'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where(['type' => 'icon|maskable', 'size' => '[0-9]+'])
    ->name('pwa.icon');

// Hydra: profile pictures and banners of users.
// Hydra: backgrounds of the login page, the dashboard and the server pages.
Route::get('/backgrounds/{file}', Base\BackgroundController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('file', '[a-z0-9-]+\.(png|jpg|gif|webp)')
    ->name('backgrounds');

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

// Hydra: images of support tickets (tickets addon).
Route::get('/tickets/attachments/{uuid}', Base\TicketAttachmentController::class)
    ->where('uuid', '[0-9a-f-]{36}')
    ->middleware('addon:tickets')
    ->name('tickets.attachments');

// Hydra: download of a database export, the link comes from the client API.
Route::get('/databases/export/{token}', Base\DatabaseExportController::class)
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('databases.export');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon)).+');
