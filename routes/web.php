<?php

use Goldnead\Assessments\Http\Controllers\Web\AssessmentController;
use Goldnead\Assessments\Http\Controllers\Web\SubmitController;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\BrandContext\Http\Middleware\SetBrandFromRouteValue;
use Illuminate\Support\Facades\Route;

/*
 * Every route here is opened without a Control Panel session. Under
 * multi-brand no brand is current then, and the fail-closed scope would hide
 * the very assessment the URL names — so the brand is derived from the
 * handle, which is unique across brands (see the migration).
 */
$prefix = trim((string) config('assessments.routes.prefix', 'a'), '/');
$brand = SetBrandFromRouteValue::class.':'.Assessment::class.',handle,handle';

Route::middleware($brand)->group(function () use ($prefix) {
    Route::get($prefix.'/{handle}', [AssessmentController::class, 'show'])
        ->name('assessments.show');

    // Keeps CSRF: the caller is a browser and a person, and the address
    // they enter becomes a contact.
    Route::post($prefix.'/{handle}/submit', SubmitController::class)
        ->middleware('throttle:'.config('assessments.routes.throttle', '20,1'))
        ->name('assessments.submit');

    Route::get($prefix.'/{handle}/r/{token}', [AssessmentController::class, 'result'])
        ->where('token', '[A-Za-z0-9]{20,64}')
        ->name('assessments.result');
});
