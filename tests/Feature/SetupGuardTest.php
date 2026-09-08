<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;

/**
 * The demo answered HTTP 500 on /cp/assessments on 03.09.2026 because the
 * addon was installed and its migrations were not. These tests reproduce that
 * database — everything present except the addon's own tables — and hold the
 * page to an empty state plus a line in the log.
 */
class SetupGuardTest extends TestCase
{
    private function admin()
    {
        return tap(User::make()->email('setup@example.test')->makeSuper())->save();
    }

    private function dropAddonTables(): void
    {
        Schema::dropIfExists('assessment_responses');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
    }

    #[Test]
    public function the_index_answers_200_when_its_tables_are_missing(): void
    {
        $this->dropAddonTables();

        $this->actingAs($this->admin())
            ->get(cp_route('assessments.index'))
            ->assertOk();
    }

    #[Test]
    public function the_index_renders_the_setup_screen_and_names_the_missing_tables(): void
    {
        $this->dropAddonTables();

        $response = $this->actingAs($this->admin())
            ->get(cp_route('assessments.index'))
            ->assertOk();

        $page = $response->viewData('page');

        $this->assertSame('assessments::SetupRequired', $page['component']);
        $this->assertContains('assessments', $page['props']['tables']);
        $this->assertNotEmpty($page['props']['heading']);
        $this->assertNotEmpty($page['props']['description']);
    }

    /**
     * The point of the guard is a readable page, not a quiet one. If this test
     * ever goes red the addon has traded a visible 500 for a silent nothing.
     */
    #[Test]
    public function the_reason_reaches_the_log(): void
    {
        $this->dropAddonTables();

        Log::spy();

        $this->actingAs($this->admin())
            ->get(cp_route('assessments.index'))
            ->assertOk();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message) => str_contains($message, 'assessments')
                && str_contains($message, 'php artisan migrate'))
            ->once();
    }

    #[Test]
    public function the_responses_page_is_guarded_too(): void
    {
        $this->dropAddonTables();

        $this->actingAs($this->admin())
            ->get(cp_route('assessments.responses.index', 1))
            ->assertOk();
    }

    #[Test]
    public function a_migrated_install_still_renders_the_listing(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(cp_route('assessments.index'))
            ->assertOk();

        $this->assertSame('assessments::Assessments/Index', $response->viewData('page')['component']);
    }
}
