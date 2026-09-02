<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Facades\Assessments;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * The Control Panel: who may do what, and what the editor refuses.
 */
class CpTest extends TestCase
{
    /**
     * @param  list<string>  $methods
     * @return list<array{name: string, method: string, uri: string}>
     */
    protected function cpRoutes(array $methods): array
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_contains($name, 'statamic.cp.assessments.')) {
                continue;
            }

            $matching = array_intersect($route->methods(), $methods);

            if ($matching === []) {
                continue;
            }

            $found[] = [
                'name' => $name,
                'method' => strtolower((string) reset($matching)),
                'uri' => '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/'),
            ];
        }

        return $found;
    }

    /** @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Stimm-Check',
            'handle' => 'stimm_check',
            'published' => true,
            'collect' => ['name' => 'optional'],
            'questions' => [
                ['text' => 'Wie oft?', 'type' => 'single', 'options' => [['label' => 'Selten', 'points' => 0], ['label' => 'Oft', 'points' => 2]]],
                ['text' => 'Wie sicher?', 'type' => 'scale', 'min' => 1, 'max' => 3, 'points_per_step' => 1],
            ],
            'scoring' => [
                ['key' => 'a', 'label' => 'A', 'min' => 1, 'max' => 2, 'text' => 'a'],
                ['key' => 'b', 'label' => 'B', 'min' => 3, 'max' => 5, 'text' => 'b'],
            ],
        ], $overrides);
    }

    #[Test]
    public function the_route_sweep_finds_what_it_means_to_check(): void
    {
        $this->assertGreaterThanOrEqual(3, count($this->cpRoutes(['POST', 'PATCH', 'DELETE'])));
        $this->assertGreaterThanOrEqual(5, count($this->cpRoutes(['GET'])));
    }

    #[Test]
    public function every_cp_route_refuses_a_user_without_the_permission(): void
    {
        $this->actingAsCpUser('nobody@example.com');
        $this->makeAssessment();

        $allowed = [];

        $headers = ['X-Inertia' => 'true', 'Accept' => 'application/json'];

        foreach ([...$this->cpRoutes(['POST', 'PATCH', 'DELETE']), ...$this->cpRoutes(['GET'])] as $route) {
            // JSON, so the `can:` middleware's AuthorizationException renders
            // as 403 rather than as a 302 back to the dashboard.
            $response = $route['method'] === 'get'
                ? $this->get($route['uri'], $headers)
                : $this->{$route['method']}($route['uri'], [], $headers);

            if ($response->getStatusCode() !== 403) {
                $allowed[] = $route['name'].' answered '.$response->getStatusCode();
            }
        }

        $this->assertSame([], $allowed, implode("\n", $allowed));
    }

    #[Test]
    public function a_viewer_reads_the_list_and_nothing_else(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAsCpUser('viewer@example.com', ['view assessments']);

        $this->get(cp_route('assessments.index'))->assertOk();
        $this->get(cp_route('assessments.edit', $assessment->id), ['Accept' => 'application/json'])->assertForbidden();
        $this->get(cp_route('assessments.responses.index', $assessment->id), ['Accept' => 'application/json'])->assertForbidden();
        $this->patch(cp_route('assessments.update', $assessment->id), $this->payload(), ['Accept' => 'application/json'])->assertForbidden();
    }

    #[Test]
    public function an_editor_creates_an_assessment_with_questions_and_levels(): void
    {
        $this->actingAsCpUser('editor@example.com', ['view assessments', 'edit assessments']);

        $this->post(cp_route('assessments.store'), $this->payload())->assertRedirect();

        $assessment = Assessment::query()->with('questions')->where('handle', 'stimm_check')->sole();

        $this->assertTrue($assessment->published);
        $this->assertCount(2, $assessment->questions);
        $this->assertSame('scale', $assessment->questions[1]->type);
        $this->assertSame([1, 5], $assessment->scoreRange());
        $this->assertSame(['a', 'b'], array_column($assessment->levels()->all(), 'key'));
    }

    #[Test]
    public function the_handle_is_derived_when_left_empty_and_refused_when_taken(): void
    {
        $this->actingAsCpUser('editor@example.com', ['view assessments', 'edit assessments']);

        $this->post(cp_route('assessments.store'), $this->payload(['handle' => null, 'title' => 'Höhen Check']))->assertRedirect();
        $this->assertNotNull(Assessments::find('hohen_check'));

        $this->post(cp_route('assessments.store'), $this->payload(['handle' => 'hohen_check']))
            ->assertSessionHasErrors(['handle']);
    }

    #[Test]
    public function overlapping_gapped_and_short_levels_are_refused(): void
    {
        $this->actingAsCpUser('editor@example.com', ['view assessments', 'edit assessments']);

        $overlap = $this->payload(['scoring' => [
            ['key' => 'a', 'label' => 'A', 'min' => 1, 'max' => 3],
            ['key' => 'b', 'label' => 'B', 'min' => 3, 'max' => 5],
        ]]);
        $this->postJson(cp_route('assessments.store'), $overlap)->assertUnprocessable()->assertJsonValidationErrors(['scoring']);

        $gap = $this->payload(['scoring' => [
            ['key' => 'a', 'label' => 'A', 'min' => 1, 'max' => 2],
            ['key' => 'b', 'label' => 'B', 'min' => 4, 'max' => 5],
        ]]);
        $this->postJson(cp_route('assessments.store'), $gap)->assertUnprocessable()->assertJsonValidationErrors(['scoring']);

        // Covers 1–4 of a 1–5 range: fine as a draft, refused when published.
        $short = $this->payload(['scoring' => [
            ['key' => 'a', 'label' => 'A', 'min' => 1, 'max' => 4],
        ]]);
        $this->postJson(cp_route('assessments.store'), $short)->assertUnprocessable()->assertJsonValidationErrors(['scoring']);
        $this->postJson(cp_route('assessments.store'), array_merge($short, ['published' => false]))->assertRedirect();

        $this->assertSame(0, Assessment::query()->where('published', true)->count());
    }

    #[Test]
    public function a_choice_question_needs_two_options_and_a_scale_needs_a_direction(): void
    {
        $this->actingAsCpUser('editor@example.com', ['view assessments', 'edit assessments']);

        $this->postJson(cp_route('assessments.store'), $this->payload(['questions' => [
            ['text' => 'Wie oft?', 'type' => 'single', 'options' => [['label' => 'Selten', 'points' => 0]]],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['questions.0.options']);

        $this->postJson(cp_route('assessments.store'), $this->payload(['questions' => [
            ['text' => 'Wie sicher?', 'type' => 'scale', 'min' => 5, 'max' => 1, 'points_per_step' => 1],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['questions.0.max']);
    }

    #[Test]
    public function updating_replaces_the_questions_and_keeps_the_handle(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAsCpUser('editor@example.com', ['view assessments', 'edit assessments']);

        $this->patch(cp_route('assessments.update', $assessment->id), $this->payload(['handle' => 'ignored']))->assertRedirect();

        $assessment->refresh()->load('questions');

        $this->assertSame('stimm_check', $assessment->handle);
        $this->assertCount(2, $assessment->questions);
        $this->assertSame('Wie oft?', $assessment->questions[0]->text);
    }

    #[Test]
    public function the_responses_page_and_the_csv_export_list_every_response(): void
    {
        $assessment = $this->makeAssessment();
        Assessments::submit($assessment, 'a@example.com', 'A', $this->validAnswers($assessment));
        Assessments::submit($assessment, 'b@example.com', null, $this->validAnswers($assessment, single: 0, multi: [2], scale: 1));

        $this->actingAsCpUser('reader@example.com', ['view assessments', 'view assessment responses']);

        $this->get(cp_route('assessments.responses.index', $assessment->id))->assertOk();

        $csv = $this->get(cp_route('assessments.responses.export', $assessment->id));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $csv->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('a@example.com;A;', $body);
        $this->assertStringContainsString(';15;weit;Weit;Täglich;"Atmung, Register";5', $body);
        $this->assertStringContainsString('b@example.com;;', $body);
        $this->assertStringContainsString(';2;anfang;"Am Anfang";Selten;Nichts;1', $body);
        $this->assertStringContainsString('Wie oft singst du?', $body);
    }

    #[Test]
    public function deleting_takes_the_responses_with_it(): void
    {
        $assessment = $this->makeAssessment();
        Assessments::submit($assessment, 'a@example.com', null, $this->validAnswers($assessment));

        $this->actingAsCpUser('editor@example.com', ['view assessments', 'edit assessments']);

        $this->delete(cp_route('assessments.destroy', $assessment->id))->assertRedirect(cp_route('assessments.index'));

        $this->assertSame(0, Assessment::query()->count());
        $this->assertSame(0, Response::query()->count());
    }
}
