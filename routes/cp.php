<?php

use Goldnead\Assessments\Http\Controllers\Cp\AssessmentController;
use Goldnead\Assessments\Http\Controllers\Cp\ResponseController;
use Goldnead\Assessments\Support\Setup;
use Goldnead\BrandContext\Http\Middleware\GuardSetup;
use Illuminate\Support\Facades\Route;

/*
 * Every route carries `can:` middleware AND the controller checks again. The
 * middleware is what a reader of this file can verify; the controller check
 * is what survives a route being re-registered elsewhere.
 *
 * The detail routes also carry the setup guard. On an install whose migrations
 * never ran it answers with the same screen the listing shows, instead of a
 * query error; it runs before route-model binding, which a guard inside the
 * controller method cannot.
 */
$guard = GuardSetup::using(Setup::class, 'assessments::messages.nav', 'assessments', 'assessment_questions', 'assessment_responses');

Route::prefix('assessments')->name('assessments.')->group(function () use ($guard) {
    Route::get('/', [AssessmentController::class, 'index'])
        ->name('index')
        ->middleware('can:view assessments');

    Route::get('/create', [AssessmentController::class, 'create'])
        ->name('create')
        ->middleware('can:edit assessments');

    Route::post('/', [AssessmentController::class, 'store'])
        ->name('store')
        ->middleware('can:edit assessments');

    Route::get('/{assessment}/edit', [AssessmentController::class, 'edit'])
        ->name('edit')
        ->whereNumber('assessment')
        ->middleware(['can:edit assessments', $guard]);

    Route::patch('/{assessment}', [AssessmentController::class, 'update'])
        ->name('update')
        ->whereNumber('assessment')
        ->middleware(['can:edit assessments', $guard]);

    Route::delete('/{assessment}', [AssessmentController::class, 'destroy'])
        ->name('destroy')
        ->whereNumber('assessment')
        ->middleware(['can:edit assessments', $guard]);

    Route::get('/{assessment}/responses', [ResponseController::class, 'index'])
        ->name('responses.index')
        ->whereNumber('assessment')
        ->middleware(['can:view assessment responses', $guard]);

    Route::get('/{assessment}/responses/export', [ResponseController::class, 'export'])
        ->name('responses.export')
        ->whereNumber('assessment')
        ->middleware(['can:view assessment responses', $guard]);
});
