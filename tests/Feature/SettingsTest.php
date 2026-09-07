<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Integrations\LeadHubBridge;
use Goldnead\Assessments\Support\Page;
use Goldnead\Assessments\Support\Settings;
use Goldnead\Assessments\Tests\TestCase;
use Goldnead\BrandContext\Facades\BrandSettings;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Einstellungen dieses Addons an der geteilten Schicht.
 *
 * Geprüft wird nicht, dass die Schicht funktioniert — das gehört in deren
 * eigene Suite — sondern dass dieses Addon richtig daran hängt, und dass ein
 * gespeicherter Wert bis zum Leser durchkommt. Die Leser sind hier
 * `Support\Page`, das den Kontext der öffentlichen Seiten baut, und die
 * LeadHub-Brücke, die entscheidet, ob eine Adresse das Addon verlässt.
 */
class SettingsTest extends TestCase
{
    #[Test]
    public function it_registers_itself_with_the_shared_settings_layer(): void
    {
        $registry = app(SettingsRegistry::class);

        $this->assertTrue($registry->has('assessments'), 'boot() hat die Einstellungen nicht angemeldet.');
        $this->assertSame(Settings::class, $registry->provider('assessments'));
        $this->assertSame('assessments', $registry->configPath('assessments'));
        $this->assertSame('manage assessments settings', $registry->permission('assessments'));
    }

    #[Test]
    public function switching_the_stylesheet_off_reaches_the_page_context(): void
    {
        $assessment = $this->makeAssessment();

        $this->assertTrue(Page::formContext($assessment)['styles']);

        BrandSettings::for('assessments')->save(['styles' => false]);

        // Der Kontext ist das, was das Template sieht. Erst hier ist belegt,
        // dass der gespeicherte Wert ankommt.
        $this->assertFalse(Page::formContext($assessment)['styles']);
    }

    #[Test]
    public function switching_the_leadhub_bridge_off_closes_it(): void
    {
        // Ohne LeadHub ist die Bruecke ohnehin zu; der Schalter muss sie auch
        // dann schliessen, wenn das Addon da ist — und das ist die Reihenfolge,
        // in der `available()` fragt: erst der Schalter, dann die Klasse.
        BrandSettings::for('assessments')->save(['integrations.leadhub' => false]);

        $this->assertFalse(config('assessments.integrations.leadhub'));
        $this->assertFalse(app(LeadHubBridge::class)->available());
    }

    #[Test]
    public function a_saved_layout_reaches_the_renderer(): void
    {
        BrandSettings::for('assessments')->save(['layout' => 'site-layout']);

        $this->assertSame('site-layout', config('assessments.layout'));
    }

    #[Test]
    public function no_key_that_is_read_while_booting_is_offered(): void
    {
        $offered = array_keys(app(SettingsRegistry::class)->fields('assessments'));

        // Routing wird beim Registrieren der Routen gelesen; die
        // Automations-Bruecke haengt sich beim Booten ein und merkt sich das.
        foreach (['routes.prefix', 'routes.throttle', 'integrations.automations'] as $key) {
            $this->assertNotContains($key, $offered);
        }
    }
}
