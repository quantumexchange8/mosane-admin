<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\TransactionController;

Route::get('locale/{locale}', function ($locale) {
    App::setLocale($locale);
    Session::put("locale", $locale);

    return redirect()->back();
});

Route::get('/', function () {
    return redirect(route('login'));
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard/Dashboard');
    })->name('dashboard');

    /**
     * ==============================
     *           Member
     * ==============================
     */
    Route::prefix('member')->group(function () {
        // listing
        Route::get('/listing', [MemberController::class, 'listing'])->name('member.listing');
        Route::get('/getMemberListingData', [MemberController::class, 'getMemberListingData'])->name('member.getMemberListingData');
        Route::get('/getFilterData', [MemberController::class, 'getFilterData'])->name('member.getFilterData');
        Route::post('/addNewMember', [MemberController::class, 'addNewMember'])->name('member.addNewMember');

        Route::post('/uploadKyc', [MemberController::class, 'uploadKyc'])->name('member.uploadKyc');

        // details
        Route::get('/detail/{id_number}', [MemberController::class, 'detail'])->name('member.detail');
        Route::get('/getUserData', [MemberController::class, 'getUserData'])->name('member.getUserData');

        Route::post('/updateContactInfo', [MemberController::class, 'updateContactInfo'])->name('member.updateContactInfo');
        Route::post('/updateCryptoWalletInfo', [MemberController::class, 'updateCryptoWalletInfo'])->name('member.updateCryptoWalletInfo');
        Route::post('/updateKYCStatus', [MemberController::class, 'updateKYCStatus'])->name('member.updateKYCStatus');
        Route::post('/walletAdjustment', [MemberController::class, 'walletAdjustment'])->name('member.walletAdjustment');
        Route::post('/accountAdjustment', [MemberController::class, 'accountAdjustment'])->name('member.accountAdjustment');
        Route::post('/accountDelete', [MemberController::class, 'accountDelete'])->name('member.accountDelete');

        // network
        Route::get('/network', [NetworkController::class, 'network'])->name('member.network');
        Route::get('/getDownlineData', [NetworkController::class, 'getDownlineData'])->name('member.getDownlineData');
    });

    /**
     * ==============================
     *            Group
     * ==============================
     */
    Route::prefix('group')->group(function () {
        Route::get('/', [GroupController::class, 'show'])->name('group');
        Route::get('/getGroups', [GroupController::class, 'getGroups'])->name('group.getGroups');
        Route::get('/getAgents', [GroupController::class, 'getAgents'])->name('group.getAgents');

        Route::post('/create_group', [GroupController::class, 'createGroup'])->name('group.create');

        Route::patch('/edit_group/{id}', [GroupController::class, 'editGroup'])->name('group.edit');

        Route::delete('/delete_group/{id}', [GroupController::class, 'deleteGroup'])->name('group.delete');
    });

    /**
     * ==============================
     *          Transaction
     * ==============================
     */
    Route::prefix('transaction')->group(function () {
        Route::get('/', [TransactionController::class, 'listing'])->name('transaction');
        Route::get('/getTransactionListingData', [TransactionController::class, 'getTransactionListingData'])->name('transaction.getTransactionListingData');
        Route::get('/getTransactionMonths', [TransactionController::class, 'getTransactionMonths'])->name('transaction.getTransactionMonths');

    });

    /**
     * ==============================
     *         Account Type
     * ==============================
     */
    Route::prefix('account_type')->group(function () {
        Route::get('/', [AccountTypeController::class, 'show'])->name('accountType');
        Route::get('/getAccountTypes', [AccountTypeController::class, 'getAccountTypes'])->name('accountType.getAccountTypes');
        Route::get('/syncAccountTypes', [AccountTypeController::class, 'syncAccountTypes'])->name('accountType.syncAccountTypes');
        Route::get('/getLevearges', [AccountTypeController::class, 'getLeverages'])->name('accountType.getLeverages');

        Route::post('/update/{id}', [AccountTypeController::class, 'updateAccountType'])->name('accountType.update');
    });

    /**
     * ==============================
     *           Profile
     * ==============================
     */
    Route::prefix('profile')->group(function () {
        Route::get('/edit', [ProfileController::class, 'edit'])->name('profile.edit');

        Route::post('/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/delete_profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

Route::get('/components/buttons', function () {
    return Inertia::render('Components/Buttons');
})->name('components.buttons');

Route::get('/test/component', function () {
    return Inertia::render('Welcome');
})->name('test.component');

require __DIR__.'/auth.php';
