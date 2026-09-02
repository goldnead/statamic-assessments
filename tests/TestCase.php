<?php

namespace Goldnead\Assessments\Tests;

use Goldnead\Assessments\Facades\Assessments;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;

/**
 * The whole suite runs with NO sibling addon installed except brand-context,
 * which is a hard dependency. LeadHub and automations are stand-ins under
 * `tests/Fakes/`, loaded by the tests that need to see a bridge fire.
 */
abstract class TestCase extends AddonTestCase
{
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getPackageProviders($app): array
    {
        return [
            \Goldnead\BrandContext\ServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);

        $app['config']->set('statamic.users.repository', 'file');

        // Free Statamic allows exactly one user, and the CP authorization
        // sweep needs at least two.
        $app['config']->set('statamic.editions.pro', true);
    }

    /**
     * Statamic's file user repository writes a YAML file per user, and
     * `RefreshDatabase` knows nothing about files.
     */
    protected function tearDown(): void
    {
        foreach (glob(__DIR__.'/__fixtures__/users/*.yaml') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * A published assessment with one question of each type and three levels.
     *
     * Range: single 0–2, multi 0–3 (1+2), scale 1–5 × 2 = 2–10, so 2–15.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeAssessment(array $overrides = []): Assessment
    {
        return Assessments::create(array_merge([
            'handle' => 'stimm_check',
            'title' => 'Stimm-Check',
            'intro' => 'Sechs Fragen.',
            'published' => true,
            'collect' => ['name' => 'optional'],
            'questions' => [
                ['text' => 'Wie oft singst du?', 'type' => 'single', 'options' => [
                    ['label' => 'Selten', 'points' => 0],
                    ['label' => 'Wöchentlich', 'points' => 1],
                    ['label' => 'Täglich', 'points' => 2],
                ]],
                ['text' => 'Was übst du?', 'type' => 'multi', 'options' => [
                    ['label' => 'Atmung', 'points' => 1],
                    ['label' => 'Register', 'points' => 2],
                    ['label' => 'Nichts', 'points' => 0],
                ]],
                ['text' => 'Wie sicher fühlst du dich in der Höhe?', 'type' => 'scale', 'min' => 1, 'max' => 5, 'points_per_step' => 2],
            ],
            'scoring' => [
                ['key' => 'anfang', 'label' => 'Am Anfang', 'min' => 2, 'max' => 6, 'text' => 'Grundlagen zuerst.'],
                ['key' => 'mitte', 'label' => 'Unterwegs', 'min' => 7, 'max' => 11, 'text' => 'Weiter so.'],
                ['key' => 'weit', 'label' => 'Weit', 'min' => 12, 'max' => 15, 'text' => 'Feinschliff.'],
            ],
        ], $overrides));
    }

    /**
     * Sign in as a CP user holding exactly the listed permissions, granted
     * through the gate so the subject stays this addon's own checks.
     *
     * @param  list<string>  $permissions
     */
    protected function actingAsCpUser(string $email, array $permissions = []): static
    {
        $allowed = array_merge(['access cp'], $permissions);

        Gate::before(fn ($user, $ability) => in_array($ability, $allowed, true) ? true : null);

        $account = User::make()->email($email);
        $account->save();

        return $this->actingAs($account);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validAnswers(Assessment $assessment, int $single = 2, array $multi = [0, 1], int $scale = 5): array
    {
        $questions = $assessment->questions()->get();

        return [
            (string) $questions[0]->id => $single,
            (string) $questions[1]->id => $multi,
            (string) $questions[2]->id => $scale,
        ];
    }
}
