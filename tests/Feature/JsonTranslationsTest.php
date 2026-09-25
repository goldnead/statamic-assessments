<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * JSON translations are global. A Statamic word with another value changes
 * Statamic's own screens ("Required" read "Pflicht" in every blueprint, "Handle"
 * read "Kennung"), and a key named like another addon renames that addon.
 */
class JsonTranslationsTest extends TestCase
{
    #[Test]
    public function the_json_translations_do_not_rename_core_or_other_addons(): void
    {
        $own = json_decode((string) file_get_contents(__DIR__.'/../../lang/de.json'), true);
        $core = json_decode((string) file_get_contents(__DIR__.'/../../vendor/statamic/cms/lang/de.json'), true);

        $others = ['Accounts', 'Activity', 'Affiliates', 'App API', 'Certificates', 'Courses', 'Email Templates',
            'Entitlements', 'Events', 'Inbox', 'Insights', 'Invoices', 'LeadHub', 'Lead Magnets', 'Marketing', 'Notifications',
            'Preference Center', 'Suppression', 'Teams', 'Webhook Manager'];

        $this->assertSame([], array_values(array_intersect($others, array_keys($own))));
        $this->assertSame([], array_keys(array_filter($own, fn (string $value, string $key): bool => isset($core[$key]) && $core[$key] !== $value, ARRAY_FILTER_USE_BOTH)));

        app()->setLocale('de');
        $this->assertSame('Pflicht', __('assessments::cp.required'));
    }
}
