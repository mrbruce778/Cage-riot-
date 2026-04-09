<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\AssetController;
// use App\Http\Controllers\OrganizationController;
// use App\Http\Controllers\ReleaseController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/change-pass', [AuthController::class, 'resetPassword']);
Route::post('/refresh', [AuthController::class, 'refresh']);

/*
|--------------------------------------------------------------------------
| Protected Routes (JWT Required)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {

    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/releases/{release}/artwork', [AssetController::class, 'uploadArtwork']);    

    /*
    |--------------------------------------------------------------------------
    | Standard Account Routes
    |--------------------------------------------------------------------------
    // */
    // Route::middleware(['role:standard_owner'])->group(function () {
    //     Route::post('/releases', [ReleaseController::class, 'store']);
    // });

    /*
    |--------------------------------------------------------------------------
    | Enterprise Parent Routes
    |--------------------------------------------------------------------------
    */
    // Route::middleware(['role:enterprise_admin'])->group(function () {
    //     Route::post('/enterprise/create-artist', [OrganizationController::class, 'createArtistAccount']);
    // });

    Route::apiResource('releases', ReleaseController::class);

    //signed url 
    Route::post('/assets/signed-upload', [AssetController::class, 'getSignedUploadUrl']);

    // Tracks
    Route::post('releases/{releaseId}/tracks', [TrackController::class, 'store']);
    Route::get('releases/{releaseId}/tracks', [TrackController::class, 'index']);
    Route::put('tracks/{id}', [TrackController::class, 'update']);
    Route::post('/tracks/{track}/asset', [TrackController::class, 'uploadAsset']);
    Route::delete('tracks/{id}', [TrackController::class, 'destroy']);
    Route::get('tracks/{id}', [TrackController::class, 'show']);
});
