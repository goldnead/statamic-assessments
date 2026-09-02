<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Support\Page;
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
            'answers' => $this->validAnswers($assessment, single: 2, multi: [0, 1], scale: 5),
        ]);

        $stored = Response::query()->sole();

        // 2 + 3 + 10
        $this->assertSame(15, $stored->score);
        $this->assertSame('weit', $stored->result_key);
        $this->assertSame('sing@example.com', $stored->email);
        $this->assertSame('Sina', $stored->name);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $stored->visit_token);
        $this->assertSame('Täglich', $stored->answers_readable[0]['answer']);

        $response->assertRedirect('/a/stimm_check/r/'.$stored->visit_token);

        $this->get('/a/stimm_check/r/'.$stored->visit_token)
            ->assertOk()
            ->assertSee('Weit')
            ->assertSee('Feinschliff.')
            ->assertSee('15 points')
            ->assertSee('Täglich')
            // The result URL is permanent and gets passed around; the address
            // is not part of what it shows.
            ->assertDontSee('sing@example.com');

        Event::assertDispatched(AssessmentCompleted::class, fn ($event) => $event->response->is($stored));
    }

    #[Test]
    public function the_token_is_minted_on_the_server_and_a_client_token_is_ignored(): void
    {
        $assessment = $this->makeAssessment();
        $payload = [
            'email' => 'sing@example.com',
            '_visit' => 'abcdefghijklmnopqrstuvwxyz0123456789',
            'answers' => $this->validAnswers($assessment),
        ];

        // Twice with the same client token: before, the second one was a 500
        // on the unique index. Now both land, each under its own token.
        $first = $this->post('/a/stimm_check/submit', $payload)->assertRedirect();
        $second = $this->post('/a/stimm_check/submit', $payload)->assertRedirect();

        $tokens = Response::query()->pluck('visit_token')->all();

        $this->assertCount(2, $tokens);
        $this->assertNotContains('abcdefghijklmnopqrstuvwxyz0123456789', $tokens);
        $this->assertNotSame($first->headers->get('Location'), $second->headers->get('Location'));
    }

    #[Test]
    public function the_same_address_may_answer_more_than_once_and_each_answer_is_its_own_event(): void
    {
        Event::fake([AssessmentCompleted::class]);

        $assessment = $this->makeAssessment();

        $this->post('/a/stimm_check/submit', ['email' => 'sing@example.com', 'answers' => $this->validAnswers($assessment)])->assertRedirect();
        $this->post('/a/stimm_check/submit', ['email' => 'sing@example.com', 'answers' => $this->validAnswers($assessment, single: 0, multi: [2], scale: 1)])->assertRedirect();

        $this->assertSame([15, 2], Response::query()->orderBy('id')->pluck('score')->all());
        Event::assertDispatchedTimes(AssessmentCompleted::class, 2);
    }

    #[Test]
    public function the_honeypot_is_hidden_without_the_stylesheet(): void
    {
        config()->set('assessments.styles', false);
        $this->makeAssessment();

        $this->get('/a/stimm_check')
            ->assertOk()
            // No stylesheet on the page, and the honeypot still hidden.
            ->assertDontSee('--as-accent', false)
            ->assertSee('class="assessment__hp" hidden', false);
    }

    #[Test]
    public function the_page_puts_only_the_title_flat_into_the_cascade(): void
    {
        $assessment = $this->makeAssessment();

        $data = Page::render('assessments::assessment', Page::formContext($assessment))->data();

        $this->assertSame(['title', 'assessment'], array_keys($data));
        $this->assertSame('Stimm-Check', $data['title']);
        $this->assertArrayHasKey('questions', $data['assessment']);
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

        foreach (['not-an-address', 'sing@localhost', 'sing@example', 'sing @example.com'] as $bad) {
            $this->postJson('/a/stimm_check/submit', ['email' => $bad, 'answers' => $this->validAnswers($assessment)])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        }

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
            'answers' => $this->validAnswers($assessment),
        ]);

        $token = Response::query()->sole()->visit_token;

        $this->get('/a/anderes/r/'.$token)->assertNotFound();
        $this->get('/a/stimm_check/r/ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    }
}
