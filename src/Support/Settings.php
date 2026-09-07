<?php

namespace Goldnead\Assessments\Support;

use Goldnead\BrandContext\Contracts\ProvidesSettings;

/**
 * Die Betriebswerte, die ein Betreiber im Control Panel ändern darf.
 *
 * Nur die Feldliste. Seite, Formular, Validierung, Speicher und Rechteprüfung
 * kommen aus `goldnead/statamic-brand-context` — siehe {@see ProvidesSettings}.
 *
 * **Was nicht hier steht, und warum.**
 *
 * - `routes.prefix` und `routes.throttle`. Beide werden beim Registrieren der
 *   Routen gelesen (`routes/web.php:15,25`), also bevor
 *   `SettingsManager::apply()` aus `app->booted()` läuft.
 * - `integrations.automations`. Wird beim Booten gelesen und dabei latched:
 *   `AutomationsBridge::register()` merkt sich in `$registered`, dass es den
 *   Auslöser angemeldet hat, und der Provider ruft es aus zwei
 *   `app->booted()`-Rückrufen. Ein späteres „aus" nimmt den Auslöser nicht aus
 *   der Bibliothek, ein späteres „an" trägt ihn nicht nach. Ein Schalter, der
 *   erst beim nächsten Deploy wirkt, ist eine Lüge in der Oberfläche.
 */
class Settings implements ProvidesSettings
{
    /**
     * Bleibt für immer stehen: der Wert steht in `brand_settings.namespace` in
     * jeder Zeile, ein neuer Name verwaist jede gespeicherte Änderung.
     */
    public static function settingsNamespace(): string
    {
        return 'assessments';
    }

    public static function settingsConfigPath(): string
    {
        return 'assessments';
    }

    public static function settingsPermission(): string
    {
        return 'manage assessments settings';
    }

    /**
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function settingsGroups(): array
    {
        return [
            [
                'title' => __('assessments::settings.groups.rendering.title'),
                'description' => __('assessments::settings.groups.rendering.description'),
                'fields' => [
                    static::field('layout', 'string'),
                    static::field('styles', 'boolean'),
                ],
            ],
            [
                'title' => __('assessments::settings.groups.integrations.title'),
                'description' => __('assessments::settings.groups.integrations.description'),
                'fields' => [
                    static::field('integrations.leadhub', 'boolean'),
                ],
            ],
        ];
    }

    /**
     * Ein Feld, mit Beschriftung und Beschreibung aus den Sprachdateien.
     *
     * Der Übersetzungsschlüssel ist der Config-Pfad mit flachgelegten Punkten:
     * ein Punkt im Sprachschlüssel ist für den Übersetzer ein Pfadtrenner.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function field(string $key, string $type, array $extra = []): array
    {
        $handle = str_replace('.', '_', $key);

        return array_merge([
            'key' => $key,
            'type' => $type,
            'label' => __("assessments::settings.fields.{$handle}.label"),
            'description' => __("assessments::settings.fields.{$handle}.description"),
            'nullable' => false,
        ], $extra);
    }
}
