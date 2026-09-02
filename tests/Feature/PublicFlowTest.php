<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

/**
 * The visitor's path: the form, the submit, the result — and what a stranger
 * must not be able to do with it.
 */
class PublicFlowTest extends TestCase
{
    #[Test]
    public function a_published_assessment_renders_its_questions(): void
    {
        $this->makeAssessment();

        $response = $this->get('/a/stimm_check');

        $response->assertOk()
            ->assertSee('Stimm-Check')
            ->assertSee('Wie oft singst du?')
            ->assertSee('Wöchentlich')
            ->assertSee('name="_visit"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('/a/stimm_check/submit');
    }

    #[Test]
    public function an_unpublished_assessment_is_not_there_for_a_visitor_but_previews_for_an_editor(): void
    {
        $this->makeAssessment(['published' => false]);

        $this->get('/a/stimm_check')->assertNotFound();

        $this->actingAsCpUser('editor@example.com', ['view assessments'])
            ->get('/a/stimm_check')
            ->assertOk()
            ->assertSee('data-preview', false);
    }

    #[Test]
    public function an_unknown_handle_is_a_404(): void
    {
        $this->get('/a/nope')->assertNotFound();
    }

    #[Test]
    public function a_complete_submit_is_scored_stored_and_shown(): void
    {
        Event::fake([AssessmentCompleted::class]);

        $assessment = $this->makeAssessment();

        $response = $this->post('/a/stimm_check/submit', [
            'email' => 'Sing@Example.com',
            'name' => 'Sina',
            '_visit' => 'abcdefghijklmnopqrstuvwxyz0123456789',
            'answers' => $this->validAnswers($assessment, single: 2, multi: [0, 1], scale: 5),
        ]);

        $stored = Response::query()->sole();

        // 2 + 3 + 10
        $this->assertSame(15, $stored->score);
        $this->assertSame('weit', $stored->result_key);
        $this->assertSame('sing@example.com', $stored->email);
        $this->assertSame('Sina', $stored->name);
        $this->assertSame('abcdefghijklmnopqrstuvwxyz0123456789', $stored->visit_token);

        $response->assertRedirect('/a/stimm_check/r/abcdefghijklmnopqrstuvwxyz0123456789');

        $this->get('/a/stimm_check/r/abcdefghijklmnopqrstuvwxyz0123456789')
            ->assertOk()
            ->assertSee('Weit')
            ->assertSee('Feinschliff.')
            ->assertSee('15 points')
            ->assertSee('Täglich');

        Event::assertDispatched(AssessmentCompleted::class, fn ($event) => $event->response->is($stored));
    }

    #[Test]
    public function a_level_with_a_redirect_sends_the_visitor_there(): void
    {
        $assessment = $this->makeAssessment(['scoring' => [
            ['key' => 'anfang', 'label' => 'Am Anfang', 'min' => 2, 'max' => 6],
            ['key' => 'weit', 'label' => 'Weit', 'min' => 7, 'max' => 15, 'redirect' => 'https://example.com/kurs'],
        ]]);

        $this->post('/a/stimm_check/submit', [
            'email' => 'sing@example.com',
            'answers' => $this->validAnswers($assessment),
        ])->assertRedirect('https://example.com/kurs');

        $this->post('/a/stimm_check/submit', [
            'email' => 'sing@example.com',
            'answers' => $this->validAnswers($assessment, single: 0, multi: [2], scale: 1),
        ])->assertRedirectContains('/a/stimm_check/r/');
    }

    #[Test]
    public function a_json_client_gets_the_result_back(): void
    {
        $assessment = $this->makeAssessment();

        $this->postJson('/a/stimm_check/submit', [
            'email' => 'sing@example.com',
            'answers' => $this->validAnswers($assessment, single: 0, multi: [2], scale: 1),
        ])->assertOk()->assertJsonPath('data.score', 2)->assertJsonPath('data.result_key', 'anfang')->assertJsonPath('data.result_label', 'Am Anfang');
    }

    #[Test]
    public function a_skipped_question_or_a_bad_address_is_refused(): void
    {
        $assessment = $this->makeAssessment();
        $answers = $this->validAnswers($assessment);
        $first = array_key_first($answers);
        unset($answers[$first]);

        $this->postJson('/a/stimm_check/submit', ['email' => 'sing@example.com', 'answers' => $answers])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers.'.$first]);

        $this->postJson('/a/stimm_check/submit', ['email' => 'not-an-address', 'answers' => $this->validAnswers($assessment)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        // An option that does not exist, and a scale value off the end.
        $this->postJson('/a/stimm_check/submit', ['email' => 'sing@example.com', 'answers' => $this->validAnswers($assessment, single: 7)])
            ->assertUnprocessable();
        $this->postJson('/a/stimm_check/submit', ['email' => 'sing@example.com', 'answers' => $this->validAnswers($assessment, scale: 6)])
            ->assertUnprocessable();

        $this->assertSame(0, Response::query()->count());
    }

    #[Test]
    public function a_required_name_is_required(): void
    {
        $assessment = $this->makeAssessment(['collect' => ['name' => 'required']]);

        $this->postJson('/a/stimm_check/submit', ['email' => 'sing@example.com', 'answers' => $this->validAnswers($assessment)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function a_filled_honeypot_stores_nothing_and_looks_like_success(): void
    {
        $assessment = $this->makeAssessment();

        $this->post('/a/stimm_check/submit', [
            'email' => 'bot@example.com',
            'website' => 'https://spam.example',
            'answers' => $this->validAnswers($assessment),
        ])->assertRedirect('/a/stimm_check');

        $this->assertSame(0, Response::query()->count());
    }

    #[Test]
    public function submitting_to_an_unpublished_assessment_is_a_404(): void
    {
        $assessment = $this->makeAssessment(['published' => false]);

        $this->post('/a/stimm_check/submit', [
            'email' => 'sing@example.com',
            'answers' => $this->validAnswers($assessment),
        ])->assertNotFound();
    }

    #[Test]
    public function a_post_without_a_csrf_token_is_a_419(): void
    {
        $assessment = $this->makeAssessment();

        // Laravel's VerifyCsrfToken steps aside while `app()->runningUnitTests()`
        // is true, which would make this test pass for an addon that had
        // dropped the `web` group entirely. For this one request the
        // application is told it is not under test.
        $this->app['env'] = 'production';

        try {
            $this->post('/a/stimm_check/submit', [
                'email' => 'sing@example.com',
                'answers' => $this->validAnswers($assessment),
            ])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, Response::query()->count());
    }

    #[Test]
    public function the_submit_route_is_throttled(): void
    {
        // The shipped default is 20 a minute, read when the routes are
        // registered — so the limit is walked up to rather than lowered.
        $assessment = $this->makeAssessment();
        $payload = ['email' => 'sing@example.com', 'answers' => $this->validAnswers($assessment)];

        for ($i = 0; $i < 20; $i++) {
            $this->post('/a/stimm_check/submit', $payload)->assertRedirect();
        }

        $this->post('/a/stimm_check/submit', $payload)->assertStatus(429);
        $this->assertSame(20, Response::query()->count());
    }

    #[Test]
    public function a_result_token_belongs_to_one_assessment(): void
    {
        $assessment = $this->makeAssessment();

        $this->post('/a/stimm_check/submit', [
            'email' => 'sing@example.com',
            '_visit' => 'abcdefghijklmnopqrstuvwxyz0123456789',
            'answers' => $this->validAnswers($assessment),
        ]);

        $this->get('/a/anderes/r/abcdefghijklmnopqrstuvwxyz0123456789')->assertNotFound();
        $this->get('/a/stimm_check/r/ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    }
}
