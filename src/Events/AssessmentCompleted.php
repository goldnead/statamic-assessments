<?php

namespace Goldnead\Assessments\Events;

use Goldnead\Assessments\Models\Response;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody finished an assessment. Fired once per stored response, after the
 * row exists, with the score and the level already on it.
 */
class AssessmentCompleted
{
    use Dispatchable;

    public function __construct(public Response $response) {}
}
