<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\DocumentShareController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicDocumentController;
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

/*
| The client's page behind a tracked link (no login).
*/
Route::prefix('d/{token}')->name('public.')->controller(PublicDocumentController::class)->group(function () {
    Route::get('/', 'show')->name('document');
    Route::get('pdf', 'pdf')->middleware('throttle:30,1')->name('pdf');
    Route::post('view', 'view')->middleware('throttle:60,1')->name('view');
    Route::post('approve', 'approve')->middleware('throttle:10,1')->name('approve');
    Route::post('reject', 'reject')->middleware('throttle:10,1')->name('reject');
    Route::post('latest', 'latest')->middleware('throttle:10,1')->name('latest');
});

// Logo is public; stamp and signature are checked inside.
Route::get('branding/{company}/{kind}', [BrandingController::class, 'show'])->name('branding.show');

/*
| The team's workspace.
*/
Route::middleware(['auth', 'member'])->group(function () {
    Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Quotations and invoices: same controller, the "type" default tells them apart.
    foreach (['quote' => 'quotes', 'invoice' => 'invoices'] as $type => $prefix) {
        Route::prefix($prefix)->name("{$prefix}.")->controller(DocumentController::class)->group(function () use ($type) {
            Route::get('/', 'index')->name('index')->defaults('type', $type);
            Route::get('create', 'create')->name('create')->defaults('type', $type);
            Route::post('/', 'store')->name('store')->defaults('type', $type);
            Route::get('{document}', 'show')->name('show')->defaults('type', $type);
            Route::get('{document}/edit', 'edit')->name('edit')->defaults('type', $type);
            Route::put('{document}', 'update')->name('update')->defaults('type', $type);
            Route::delete('{document}', 'destroy')->name('destroy')->defaults('type', $type);
            Route::post('{document}/duplicate', 'duplicate')->name('duplicate')->defaults('type', $type);
            Route::post('{document}/revise', 'revise')->name('revise')->defaults('type', $type);
            Route::post('{document}/convert', 'convert')->name('convert')->defaults('type', $type);
        });
        Route::prefix($prefix)->name("{$prefix}.")->controller(DocumentShareController::class)->group(function () use ($type) {
            Route::post('{document}/send', 'store')->name('send')->defaults('type', $type);
            Route::post('{document}/extend', 'extend')->name('extend')->defaults('type', $type);
        });
        Route::get("{$prefix}/{document}/pdf", DocumentPdfController::class)->name("{$prefix}.pdf")->defaults('type', $type);
        Route::post("{$prefix}/{document}/comments", [CommentController::class, 'store'])->name("{$prefix}.comments.store")->defaults('type', $type);
    }

    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Every member can browse clients and the catalog; editing needs the matching permission.
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/search', [ClientController::class, 'search'])->name('clients.search');
    Route::middleware('can:manage_clients')->group(function () {
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    });

    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('items/search', [ItemController::class, 'search'])->name('items.search');
    Route::middleware('can:manage_items')->group(function () {
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    });

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
