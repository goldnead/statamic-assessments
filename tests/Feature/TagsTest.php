<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Facades\Assessments;
use Goldnead\Assessments\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;

/**
 * The Antlers surface a site builds on: the URL, the form variables, the result.
 */
class TagsTest extends TestCase
{
    /**
     * The third argument marks the template as trusted. Without it Antlers
     * treats the string as user content and runs no tags at all, which reads
     * as "the tag renders nothing" and is not what is under test.
     */
    protected function render(string $template, array $data = []): string
    {
        return (string) Antlers::parse($template, $data, true);
    }

    #[Test]
    public function the_url_tag_links_a_published_assessment_and_nothing_else(): void
    {
        $this->makeAssessment();
        $this->makeAssessment(['handle' => 'entwurf', 'published' => false]);

        $this->assertSame('http://localhost/a/stimm_check', $this->render('{{ assessments:url handle="stimm_check" }}'));
        $this->assertSame('', $this->render('{{ assessments:url handle="entwurf" }}'));
        $this->assertSame('', $this->render('{{ assessments:url handle="nope" }}'));
    }

    #[Test]
    public function the_form_tag_hands_over_the_questions_and_the_action(): void
    {
        $this->makeAssessment();

        $html = $this->render('{{ assessments:form handle="stimm_check" }}<form action="{{ action }}">{{ questions }}<p>{{ text }}</p>{{ /questions }}</form>{{ /assessments:form }}');

        $this->assertStringContainsString('action="http://localhost/a/stimm_check/submit"', $html);
        $this->assertStringContainsString('<p>Wie oft singst du?</p>', $html);
        $this->assertStringContainsString('<p>Wie sicher fühlst du dich in der Höhe?</p>', $html);
    }

    #[Test]
    public function the_result_tag_reads_the_token_and_says_nothing_without_one(): void
    {
        $assessment = $this->makeAssessment();
        $response = Assessments::submit($assessment, 'sing@example.com', null, $this->validAnswers($assessment));

        $template = '{{ assessments:result token="'.$response->visit_token.'" }}{{ result_label }}/{{ score }}/{{ email }}{{ /assessments:result }}';

        $this->assertSame('Weit/15/', $this->render($template));
        $this->assertSame('', $this->render('{{ assessments:result token="ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ" }}{{ result_label }}{{ /assessments:result }}'));
        $this->assertSame('', $this->render('{{ assessments:result }}{{ result_label }}{{ /assessments:result }}'));
    }
}
