<?php

namespace Goldnead\Assessments\Listeners;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Integrations\Automations\AutomationsBridge;

/** Autoloaded by core off the first parameter type below. */
class StartAutomations
{
    public function __construct(protected AutomationsBridge $bridge) {}

    public function handle(AssessmentCompleted $event): void
    {
        $this->bridge->dispatch($event);
    }
}
