<?php

namespace Goldnead\Assessments\Integrations;

use Goldnead\Assessments\AssessmentsManager;
use Goldnead\Assessments\Models\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The optional path from a completed assessment to a contact.
 *
 * Off unless the sibling is installed and the site left the switch on. The
 * address becomes a contact **without consent**: answering a questionnaire is
 * not agreeing to mail, and the result is written as a timeline event the
 * site can act on with a tag, a sequence or an automation.
 *
 * Two rules this family learned the hard way: `class_exists` on the facade
 * actually called, and never `method_exists()` on a facade class — it
 * declares none of what it forwards, so the probe is always false. The object
 * behind it is asked instead.
 */
class LeadHubBridge
{
    protected const FACADE = '\Goldnead\Leadhub\Facades\LeadHub';

    public const EVENT_TYPE = 'assessment.completed';

    public function __construct(protected AssessmentsManager $assessments) {}

    public function available(): bool
    {
        if (! config('assessments.integrations.leadhub', true)) {
            return false;
        }

        $facade = self::FACADE;

        if (! class_exists($facade)) {
            return false;
        }

        try {
            $root = $facade::getFacadeRoot();
        } catch (Throwable) {
            return false;
        }

        return is_object($root) && method_exists($root, 'ingest');
    }

    /**
     * A failure here never fails the submit. The visitor is looking at their
     * result; the response is already stored either way.
     */
    public function record(Response $response): void
    {
        if (! $this->available()) {
            return;
        }

        $assessment = $response->assessment;
        $level = $response->level();

        try {
            $facade = self::FACADE;

            $event = $facade::ingest([
                'email' => $response->email,
                'type' => self::EVENT_TYPE,
                'summary' => __('assessments::messages.leadhub_summary', [
                    'title' => $assessment->title,
                    'level' => $level['label'] ?? '–',
                    'score' => $response->score,
                ]),
                'source' => 'assessment:'.$assessment->handle,
                'source_type' => 'assessment_response',
                'source_id' => $response->getKey(),
                'dedupe_key' => 'assessment:response:'.$response->getKey(),
                'occurred_at' => $response->created_at,
                'payload' => [
                    'assessment' => $assessment->handle,
                    'assessment_title' => $assessment->title,
                    'score' => $response->score,
                    'result_key' => $response->result_key,
                    'result_label' => $level['label'] ?? null,
                    'answers' => $this->assessments->readableAnswers($response),
                ],
                'contact' => array_filter(['full_name' => $response->name]),
            ]);

            // The contact the event landed on, if the sibling says. Read
            // loosely: an older release may return nothing or something else.
            $contactId = is_object($event) ? ($event->contact_id ?? null) : null;

            if (is_numeric($contactId)) {
                $response->forceFill(['contact_id' => (int) $contactId])->saveQuietly();
            }
        } catch (Throwable $e) {
            Log::warning('statamic-assessments: handing the response to LeadHub failed; the response is stored.', [
                'response' => $response->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
