<?php

/*
 * Die Beschriftungen der Einstellungsseite.
 *
 * Feldschlüssel sind der Config-Pfad mit flachgelegten Punkten
 * (`integrations.leadhub` → `integrations_leadhub`).
 */

return [

    'permission_manage' => 'Assessment-Einstellungen verwalten',

    'groups' => [

        'rendering' => [
            'title' => 'Darstellung',
            'description' => 'Wie die öffentlichen Seiten eines Assessments aussehen. Der URL-Präfix und die Anfragebremse stehen weiterhin in der Datei config/assessments.php: beide werden beim Registrieren der Routen gelesen und wirkten hier erst beim nächsten Deploy.',
        ],

        'integrations' => [
            'title' => 'Nachbar-Addons',
            'description' => 'Was ein abgeschlossenes Assessment weitergibt. Der Schalter für Automations steht weiterhin nur in der Config: der Auslöser wird beim Booten angemeldet und das gemerkt, ein späteres Umlegen nimmt ihn weder aus der Bibliothek noch trägt es ihn nach.',
        ],

    ],

    'fields' => [

        'layout' => [
            'label' => 'Antlers-Layout',
            'description' => 'In dieses Layout der Seite werden die mitgelieferten Templates gehüllt. Gibt es die View nicht, fällt das Addon auf seine eigene schlichte Hülle zurück, statt einen Fehler zu zeigen.',
        ],

        'styles' => [
            'label' => 'Mitgeliefertes Stylesheet laden',
            'description' => 'Aus heißt: die Templates binden das Stylesheet nicht mehr ein. Das Markup und seine Klassennamen bleiben, eigenes CSS greift also weiter.',
        ],

        'integrations_leadhub' => [
            'label' => 'Adressen an LeadHub geben',
            'description' => 'An heißt: die im Assessment erfasste Adresse geht als Kontakt ans CRM, ohne dass eine Einwilligung vorliegt, und das Ergebnis wird als Ereignis dort vermerkt. Aus heißt: die Adresse bleibt in diesem Addon.',
        ],

    ],

];
