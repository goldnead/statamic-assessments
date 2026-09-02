<?php

namespace Goldnead\Assessments\Http\Controllers\Web;

use Goldnead\Assessments\AssessmentsManager;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Support\Page;
use Illuminate\Http\Request;

/**
 * The public side: the questionnaire, and the result afterwards.
 */
class AssessmentController
{
    public function __construct(protected AssessmentsManager $assessments) {}

    public function show(Request $request, string $handle)
    {
        $assessment = $this->assessments->find($handle);

        abort_unless($assessment !== null, 404);

        // An unpublished assessment is visible to somebody who may edit it in
        // the Control Panel, and to nobody else. 404 rather than 403: from
        // outside there is nothing here.
        $preview = ! $assessment->published;

        abort_if($preview && ! $this->canPreview($request), 404);

        return Page::render(
            'assessments::assessment',
            Page::formContext($assessment, $preview)
        );
    }

    /**
     * The result page, under a token only the person who just submitted has.
     *
     * A page of its own rather than the POST response, so a reload does not
     * resubmit and the address can be sent on.
     */
    public function result(Request $request, string $handle, string $token)
    {
        $response = Response::query()
            ->with('assessment.questions')
            ->where('visit_token', $token)
            ->first();

        abort_unless($response !== null && $response->assessment?->handle === $handle, 404);

        return Page::render(
            'assessments::result',
            Page::resultContext($response, $this->assessments->readableAnswers($response))
        );
    }

    protected function canPreview(Request $request): bool
    {
        return (bool) $request->user()?->can('view assessments');
    }
}
