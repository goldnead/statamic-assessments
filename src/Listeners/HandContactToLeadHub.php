<?php

namespace Goldnead\Assessments\Listeners;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Integrations\LeadHubBridge;

/** Autoloaded by core off the first parameter type below. */
class HandContactToLeadHub
{
    public function __construct(protected LeadHubBridge $bridge) {}

    public function handle(AssessmentCompleted $event): void
    {
        $this->bridge->record($event->response);
    }
}
