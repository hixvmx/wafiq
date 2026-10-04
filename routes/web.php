<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
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
});
