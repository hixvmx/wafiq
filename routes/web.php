<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\DocumentDefaultsController;
use App\Http\Controllers\Settings\MessageTemplatesController;
use App\Http\Controllers\Settings\NumberingController;
use App\Http\Controllers\Settings\TaxController;
use App\Http\Controllers\Team\InvitationController;
use App\Http\Controllers\Team\MemberController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Login with a magic link (no passwords).
*/
Route::middleware('guest')->controller(LoginController::class)->group(function () {
    Route::get('login', 'create')->name('login');
    Route::post('login', 'store')->name('login.send');
    Route::get('login/{token}', 'show')->name('login.confirm');
    Route::post('login/{token}', 'confirm')->middleware('throttle:10,1')->name('login.redeem');
});

Route::controller(AcceptInvitationController::class)->group(function () {
    Route::get('invitations/{token}', 'show')->name('invitations.show');
    Route::post('invitations/{token}', 'accept')->middleware('throttle:10,1')->name('invitations.accept');
});

// Logo is public; stamp and signature are checked inside.
Route::get('branding/{company}/{kind}', [BrandingController::class, 'show'])->name('branding.show');

/*
| The team's workspace.
*/
Route::middleware(['auth', 'member'])->group(function () {
    Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('can:manage_team')->prefix('team')->name('team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });

    Route::middleware('can:manage_settings')->prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/settings/company')->name('index');

        Route::get('company', [CompanyProfileController::class, 'edit'])->name('company');
        Route::put('company', [CompanyProfileController::class, 'update']);

        Route::get('branding', [BrandingController::class, 'edit'])->name('branding');
        Route::put('branding', [BrandingController::class, 'update']);
        Route::post('branding/{kind}', [BrandingController::class, 'upload'])->name('branding.upload');
        Route::delete('branding/{kind}', [BrandingController::class, 'destroy'])->name('branding.destroy');

        Route::get('taxes', [TaxController::class, 'index'])->name('taxes');
        Route::post('taxes', [TaxController::class, 'store'])->name('taxes.store');
        Route::put('taxes/{taxRate}', [TaxController::class, 'update'])->name('taxes.update');
        Route::delete('taxes/{taxRate}', [TaxController::class, 'destroy'])->name('taxes.destroy');
        Route::put('currencies', [TaxController::class, 'updateCurrencies'])->name('currencies.update');

        Route::get('numbering', [NumberingController::class, 'edit'])->name('numbering');
        Route::put('numbering', [NumberingController::class, 'update']);

        Route::get('documents', [DocumentDefaultsController::class, 'edit'])->name('documents');
        Route::put('documents', [DocumentDefaultsController::class, 'update']);

        Route::get('messages', [MessageTemplatesController::class, 'edit'])->name('messages');
        Route::put('messages', [MessageTemplatesController::class, 'update']);
    });
});
