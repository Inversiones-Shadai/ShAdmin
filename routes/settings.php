<?php

use App\Http\Middleware\EnsureLocalAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', fn () => redirect()->route(
        filled(config('services.shadmin_api.base_url')) ? 'appearance.edit' : 'profile.edit',
    ))->name('settings');

    Route::livewire('settings/profile', 'pages::settings.profile')
        ->middleware(EnsureLocalAccount::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            EnsureLocalAccount::class,
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->middleware(EnsureLocalAccount::class)->name('well-known.passkeys');
