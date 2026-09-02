<?php

namespace Goldnead\Assessments\Integrations\Automations;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Integrations\Automations\Triggers\AssessmentCompletedTrigger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registers the trigger with the automations addon and hands it the event.
 *
 * Both halves are guarded by `class_exists` on the sibling's own classes, so
 * this file compiles and runs on a site without automations. The trigger
 * class itself names the sibling's contract in `implements`, which is safe
 * because nothing touches it unless the facade exists.
 */
class AutomationsBridge
{
    protected const FACADE = '\Goldnead\StatamicAutomations\Facades\Automations';

    protected const DISPATCHER = '\Goldnead\StatamicAutomations\Engine\TriggerDispatcher';

    protected bool $registered = false;

    public function available(): bool
    {
        return (bool) config('assessments.integrations.automations', true)
            && class_exists(self::FACADE)
            && class_exists(self::DISPATCHER);
    }

    /**
     * Idempotent: the booted callbacks fire more than once in a Statamic
     * application, and a trigger registered twice is two entries in the
     * node library.
     */
    public function register(): void
    {
        if ($this->registered || ! $this->available()) {
            return;
        }

        try {
            $facade = self::FACADE;
            $root = $facade::getFacadeRoot();

            if (! is_object($root) || ! method_exists($root, 'registerTrigger')) {
                return;
            }

            $root->registerTrigger(AssessmentCompletedTrigger::class);
            $this->registered = true;
        } catch (Throwable $e) {
            Log::warning('statamic-assessments: the automations trigger could not be registered.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Start every enabled automation that begins on `assessments.completed`.
     *
     * Through the sibling's own dispatcher, so matching, enrollment policy
     * and the sync/async choice are its rules, not a copy of them.
     */
    public function dispatch(AssessmentCompleted $event): void
    {
        if (! $this->available()) {
            return;
        }

        try {
            app(self::DISPATCHER)->dispatch(AssessmentCompletedTrigger::handle(), $event);
        } catch (Throwable $e) {
            Log::warning('statamic-assessments: dispatching the automations trigger failed; the response is stored.', [
                'response' => $event->response->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
