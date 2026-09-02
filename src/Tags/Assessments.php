<?php

namespace Goldnead\Assessments\Tags;

use Goldnead\Assessments\AssessmentsManager;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Support\Page;
use Statamic\Tags\Tags;

/**
 * Assessments inside a site's own templates.
 *
 * {{ assessments:url handle="stimm-check" }}
 *     — where the questionnaire lives.
 *
 * {{ assessments:form handle="stimm-check" }} … {{ /assessments:form }}
 *     — the same variables the shipped template gets (`questions`, `action`,
 *       `ask_name` …), flat, for a page that draws the form itself.
 *
 * {{ assessments:result }} … {{ /assessments:result }}
 *     — the result for a token, from the `token` parameter or the URL's
 *       `r` query string; nothing when there is none.
 */
class Assessments extends Tags
{
    protected static $handle = 'assessments';

    public function url(): string
    {
        $assessment = $this->assessment();

        return $assessment ? route('assessments.show', $assessment->handle) : '';
    }

    public function form(): array|string
    {
        $assessment = $this->assessment();

        if (! $assessment) {
            return $this->parseNoResults();
        }

        return $this->parse(Page::formContext($assessment));
    }

    public function result(): array|string
    {
        $token = (string) ($this->params->get('token') ?: request()->query('r', ''));

        if (! preg_match('/^[A-Za-z0-9]{20,64}$/', $token)) {
            return $this->parseNoResults();
        }

        $response = Response::query()->with('assessment.questions')->where('visit_token', $token)->first();

        if (! $response || ! $response->assessment) {
            return $this->parseNoResults();
        }

        return $this->parse(Page::resultContext($response, app(AssessmentsManager::class)->readableAnswers($response)));
    }

    protected function assessment()
    {
        $handle = (string) $this->params->get('handle', '');

        if ($handle === '') {
            return null;
        }

        $assessment = app(AssessmentsManager::class)->find($handle);

        // An unpublished assessment is not linkable from a page.
        return $assessment && $assessment->published ? $assessment : null;
    }
}
