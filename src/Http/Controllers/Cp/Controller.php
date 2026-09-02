<?php

namespace Goldnead\Assessments\Http\Controllers\Cp;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Abort with 403 unless the current user holds $permission.
     *
     * Through Laravel's Gate (`$user->can()`) rather than Statamic's
     * `User::hasPermission()`: Statamic's `Gate::after` hook resolves the
     * Statamic user and short-circuits super users, so `can()` is right for
     * both the file and the eloquent users repository.
     */
    protected function authorizeOrFail(Request $request, string $permission): void
    {
        if (! $this->userCan($request, $permission)) {
            abort(403);
        }
    }

    protected function userCan(Request $request, string $permission): bool
    {
        return (bool) $request->user()?->can($permission);
    }
}
