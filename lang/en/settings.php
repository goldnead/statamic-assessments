<?php

/*
 * Labels for the settings screen.
 *
 * Field keys are the config path with the dots flattened
 * (`integrations.leadhub` → `integrations_leadhub`).
 */

return [

    'permission_manage' => 'Manage assessment settings',

    'groups' => [

        'rendering' => [
            'title' => 'Rendering',
            'description' => 'What the public pages of an assessment look like. The URL prefix and the rate limit stay in config/assessments.php: both are read while the routes are registered and would only take effect on the next deploy.',
        ],

        'integrations' => [
            'title' => 'Sibling addons',
            'description' => 'What a completed assessment hands on. The automations switch stays in the config alone: the trigger is registered while booting and that is remembered, so flipping it later neither removes it from the library nor adds it.',
        ],

    ],

    'fields' => [

        'layout' => [
            'label' => 'Antlers layout',
            'description' => 'The site layout the shipped templates are wrapped in. When the view does not exist the addon falls back to its own plain shell rather than showing an error.',
        ],

        'styles' => [
            'label' => 'Load the shipped stylesheet',
            'description' => 'Off means the templates no longer link the stylesheet. The markup and its class names stay, so your own CSS still applies.',
        ],

        'integrations_leadhub' => [
            'label' => 'Hand addresses to LeadHub',
            'description' => 'On means the address captured in an assessment goes to the CRM as a contact without consent, and the result is recorded there as an event. Off keeps the address inside this addon.',
        ],

    ],

];
