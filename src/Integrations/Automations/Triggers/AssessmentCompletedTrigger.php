<?php

namespace Goldnead\Assessments\Integrations\Automations\Triggers;

use Goldnead\Assessments\Facades\Assessments;
use Goldnead\Assessments\Integrations\Automations\AutomationsBridge;
use Goldnead\Assessments\Models\Response;
use Goldnead\StatamicAutomations\Context\AutomationContext;
use Goldnead\StatamicAutomations\Contracts\AutomationTrigger;

/**
 * Somebody finished an assessment.
 *
 * Filterable by assessment and by result level, both optional, so one
 * automation can say "anyone who scored `advanced` on the voice check" and
 * another "anyone who finished anything".
 *
 * Only loaded when the automations addon is installed — see
 * {@see AutomationsBridge}.
 */
class AssessmentCompletedTrigger implements AutomationTrigger
{
    public static function handle(): string
    {
        return 'assessments.completed';
    }

    public static function label(): string
    {
        return __('assessments::messages.trigger_label');
    }

    public static function description(): ?string
    {
        return __('assessments::messages.trigger_description');
    }

    public static function group(): string
    {
        return 'Assessments';
    }

    public static function supportsTestMode(): bool
    {
        return true;
    }

    public static function schema(): array
    {
        return [
            [
                'handle' => 'assessment',
                'label' => __('assessments::messages.trigger_field_assessment'),
                'type' => 'text',
                'required' => false,
                'help' => __('assessments::messages.trigger_field_assessment_help'),
            ],
            [
                'handle' => 'result_key',
                'label' => __('assessments::messages.trigger_field_result'),
                'type' => 'text',
                'required' => false,
                'help' => __('assessments::messages.trigger_field_result_help'),
            ],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'response' => [
                'id' => 'integer',
                'email' => 'string',
                'name' => 'string',
                'score' => 'integer',
                'result_key' => 'string',
                'result_label' => 'string',
                'assessment' => 'string',
                'assessment_title' => 'string',
                'answers' => 'array',
                'completed_at' => 'datetime',
            ],
        ];
    }

    public function matches(object|array $event, array $config): bool
    {
        $response = $this->responseOf($event);

        if ($response === null) {
            return false;
        }

        $assessment = trim((string) ($config['assessment'] ?? ''));
        $level = trim((string) ($config['result_key'] ?? ''));

        if ($assessment !== '' && $response->assessment?->handle !== $assessment) {
            return false;
        }

        if ($level !== '' && $response->result_key !== $level) {
            return false;
        }

        return true;
    }

    public function buildContext(object|array $event, array $config): AutomationContext
    {
        $response = $this->responseOf($event);

        if ($response === null) {
            return AutomationContext::make(['response' => []]);
        }

        return AutomationContext::make([
            'response' => [
                'id' => $response->getKey(),
                'email' => $response->email,
                'name' => $response->name,
                'score' => $response->score,
                'result_key' => $response->result_key,
                'result_label' => $response->resultLabel(),
                'assessment' => $response->assessment?->handle,
                'assessment_title' => $response->assessment?->title,
                'answers' => Assessments::readableAnswers($response),
                'completed_at' => $response->created_at?->toIso8601String(),
            ],
        ]);
    }

    protected function responseOf(object|array $event): ?Response
    {
        $response = is_array($event) ? ($event['response'] ?? null) : ($event->response ?? null);

        return $response instanceof Response ? $response : null;
    }
}
