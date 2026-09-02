<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Facades\Assessments;
use Goldnead\Assessments\Integrations\Automations\AutomationsBridge;
use Goldnead\Assessments\Integrations\Automations\Triggers\AssessmentCompletedTrigger;
use Goldnead\Assessments\Integrations\LeadHubBridge;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Tests\TestCase;
use Goldnead\Leadhub\Facades\LeadHub;
use Goldnead\StatamicAutomations\Engine\TriggerDispatcher;
use Goldnead\StatamicAutomations\Facades\Automations;
use PHPUnit\Framework\Attributes\Test;

/**
 * The two bridges, against stand-ins of the siblings.
 *
 * The stand-ins are loaded before the application boots, so the trigger
 * registration in the provider's booted callback sees them the way it would
 * see the installed packages.
 */
class IntegrationsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__.'/../Fakes/leadhub-facade.php';
        require_once __DIR__.'/../Fakes/automations-contracts.php';

        LeadHub::$ingested = [];
        Automations::$registered = [];
        TriggerDispatcher::$dispatched = [];

        parent::setUp();
    }

    protected function complete(int $single = 2, array $multi = [0, 1], int $scale = 5): Response
    {
        $assessment = $this->makeAssessment();

        return Assessments::submit($assessment, 'sing@example.com', 'Sina', $this->validAnswers($assessment, $single, $multi, $scale));
    }

    #[Test]
    public function a_completed_assessment_becomes_a_contact_event_without_consent(): void
    {
        $this->assertTrue(app(LeadHubBridge::class)->available());

        $response = $this->complete();

        $this->assertCount(1, LeadHub::$ingested);

        $event = LeadHub::$ingested[0];

        $this->assertSame('sing@example.com', $event['email']);
        $this->assertSame('assessment.completed', $event['type']);
        $this->assertSame('assessment:stimm_check', $event['source']);
        $this->assertSame('assessment:response:'.$response->id, $event['dedupe_key']);
        $this->assertSame(15, $event['payload']['score']);
        $this->assertSame('weit', $event['payload']['result_key']);
        $this->assertSame('Weit', $event['payload']['result_label']);
        $this->assertSame('stimm_check', $event['payload']['assessment']);
        $this->assertSame('Täglich', $event['payload']['answers'][0]['answer']);
        $this->assertSame(['full_name' => 'Sina'], $event['contact']);
        $this->assertStringContainsString('Weit', $event['summary']);
        $this->assertStringContainsString('15', $event['summary']);

        // Answering a questionnaire is not agreeing to mail.
        $this->assertArrayNotHasKey('consent', $event);
        $this->assertArrayNotHasKey('tags', $event);

        // The contact the sibling reported lands on the response.
        $this->assertSame(4711, $response->fresh()->contact_id);
    }

    #[Test]
    public function the_leadhub_handover_can_be_switched_off(): void
    {
        config()->set('assessments.integrations.leadhub', false);

        $this->complete();

        $this->assertSame([], LeadHub::$ingested);
    }

    #[Test]
    public function the_trigger_is_registered_with_automations_at_boot(): void
    {
        $this->assertTrue(app(AutomationsBridge::class)->available());
        $this->assertContains(AssessmentCompletedTrigger::class, Automations::$registered);
        // Once, although Statamic's booted callbacks fire more than once.
        $this->assertCount(1, array_keys(Automations::$registered, AssessmentCompletedTrigger::class, true));
    }

    #[Test]
    public function completing_hands_the_event_to_the_automations_dispatcher(): void
    {
        $response = $this->complete();

        $this->assertCount(1, TriggerDispatcher::$dispatched);
        $this->assertSame('assessments.completed', TriggerDispatcher::$dispatched[0]['handle']);
        $this->assertInstanceOf(AssessmentCompleted::class, TriggerDispatcher::$dispatched[0]['event']);
        $this->assertTrue(TriggerDispatcher::$dispatched[0]['event']->response->is($response));
    }

    #[Test]
    public function the_trigger_filters_by_assessment_and_level_and_builds_the_context(): void
    {
        $response = $this->complete();
        $event = new AssessmentCompleted($response);
        $trigger = new AssessmentCompletedTrigger;

        $this->assertSame('assessments.completed', AssessmentCompletedTrigger::handle());
        $this->assertSame(['assessment', 'result_key'], array_column(AssessmentCompletedTrigger::schema(), 'handle'));
        $this->assertArrayHasKey('response', AssessmentCompletedTrigger::outputSchema());

        $this->assertTrue($trigger->matches($event, []));
        $this->assertTrue($trigger->matches($event, ['assessment' => 'stimm_check']));
        $this->assertTrue($trigger->matches($event, ['assessment' => 'stimm_check', 'result_key' => 'weit']));
        $this->assertFalse($trigger->matches($event, ['assessment' => 'anderes']));
        $this->assertFalse($trigger->matches($event, ['result_key' => 'anfang']));
        $this->assertFalse($trigger->matches(['response' => null], []));

        $context = $trigger->buildContext($event, []);

        $this->assertSame('sing@example.com', $context->get('response.email'));
        $this->assertSame('Sina', $context->get('response.name'));
        $this->assertSame(15, $context->get('response.score'));
        $this->assertSame('weit', $context->get('response.result_key'));
        $this->assertSame('Weit', $context->get('response.result_label'));
        $this->assertSame('stimm_check', $context->get('response.assessment'));
        $this->assertSame('Stimm-Check', $context->get('response.assessment_title'));
        $this->assertCount(3, $context->get('response.answers'));
        $this->assertNotNull($context->get('response.completed_at'));
    }

    #[Test]
    public function the_automations_handover_can_be_switched_off(): void
    {
        config()->set('assessments.integrations.automations', false);

        $this->complete();

        $this->assertSame([], TriggerDispatcher::$dispatched);
    }
}
