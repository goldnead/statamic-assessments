<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public routes
    |--------------------------------------------------------------------------
    |
    | An assessment answers under `/{prefix}/{handle}`; the form posts to
    | `/{prefix}/{handle}/submit`; the result page is `/{prefix}/{handle}/r/{token}`.
    |
    | `throttle` is a Laravel rate-limit expression per address. Twenty a
    | minute lets a class of students submit together and still stops a script.
    |
    */

    'routes' => [
        'prefix' => 'a',
        'throttle' => '20,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Front-end rendering
    |--------------------------------------------------------------------------
    |
    | `layout` is the site's Antlers layout the shipped templates are wrapped
    | in. When the view does not exist the addon falls back to its own plain
    | shell, so a fresh site sees a working page before it has written one.
    |
    | `styles` links the shipped stylesheet from inside the templates. A site
    | with its own design turns it off and keeps the markup.
    |
    */

    'layout' => 'layout',
    'styles' => true,

    /*
    |--------------------------------------------------------------------------
    | Siblings
    |--------------------------------------------------------------------------
    |
    | Both are used only when the package is installed. `leadhub` hands the
    | address to the CRM as a contact **without consent** and records the
    | result as the event `assessment.completed`. `automations` registers the
    | trigger `assessments.completed`.
    |
    */

    'integrations' => [
        'leadhub' => true,
        'automations' => true,
    ],

];
