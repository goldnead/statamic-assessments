<?php

namespace Goldnead\Assessments\Tests\Feature;

use Goldnead\Assessments\Facades\Assessments;
use Goldnead\Assessments\Models\Question;
use Goldnead\Assessments\Scoring\Levels;
use Goldnead\Assessments\Scoring\Scorer;
use Goldnead\Assessments\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

/**
 * The arithmetic, one type at a time, and the level rules at their edges.
 */
class ScoringTest extends TestCase
{
    #[Test]
    public function single_choice_earns_the_points_of_the_chosen_option(): void
    {
        $question = new Question(['type' => 'single', 'options' => [
            ['label' => 'a', 'points' => 0], ['label' => 'b', 'points' => 3], ['label' => 'c', 'points' => 5],
        ]]);

        $scorer = new Scorer;

        $this->assertSame(3, $scorer->pointsFor($question, 1));
        $this->assertSame(3, $scorer->pointsFor($question, '1'));
        $this->assertSame(0, $scorer->pointsFor($question, 9));
        $this->assertSame(0, $scorer->pointsFor($question, null));
        $this->assertSame(0, $scorer->pointsFor($question, 'x'));
    }

    #[Test]
    public function multiple_choice_sums_the_chosen_options_once_each(): void
    {
        $question = new Question(['type' => 'multi', 'options' => [
            ['label' => 'a', 'points' => 1], ['label' => 'b', 'points' => 2], ['label' => 'c', 'points' => -1],
        ]]);

        $scorer = new Scorer;

        $this->assertSame(3, $scorer->pointsFor($question, [0, 1]));
        $this->assertSame(2, $scorer->pointsFor($question, [0, 1, 2]));
        // The same option twice is still one option.
        $this->assertSame(1, $scorer->pointsFor($question, ['0', '0']));
        $this->assertSame(0, $scorer->pointsFor($question, []));
        $this->assertSame(0, $scorer->pointsFor($question, 1));
    }

    #[Test]
    public function a_scale_multiplies_the_value_by_the_step(): void
    {
        $question = new Question(['type' => 'scale', 'min' => 1, 'max' => 5, 'points_per_step' => 2]);

        $scorer = new Scorer;

        $this->assertSame(2, $scorer->pointsFor($question, 1));
        $this->assertSame(10, $scorer->pointsFor($question, '5'));
        // Out of range earns nothing rather than 12.
        $this->assertSame(0, $scorer->pointsFor($question, 6));
        $this->assertSame(0, $scorer->pointsFor($question, 0));
    }

    #[Test]
    public function the_total_is_the_sum_over_the_questions(): void
    {
        $assessment = $this->makeAssessment();

        $result = Assessments::score($assessment, $this->validAnswers($assessment, single: 1, multi: [1], scale: 3));

        // 1 + 2 + 3×2
        $this->assertSame(9, $result['score']);
        $this->assertSame([1, 2, 6], array_values($result['breakdown']));
    }

    #[Test]
    public function the_achievable_range_follows_the_questions(): void
    {
        $assessment = $this->makeAssessment();

        $this->assertSame([2, 15], $assessment->scoreRange());
    }

    #[Test]
    public function levels_are_inclusive_on_both_ends(): void
    {
        $levels = Levels::fromArray([
            ['key' => 'b', 'label' => 'B', 'min' => 5, 'max' => 9],
            ['key' => 'a', 'label' => 'A', 'min' => 0, 'max' => 4],
            ['key' => 'c', 'label' => 'C', 'min' => 10, 'max' => 12],
        ]);

        $this->assertSame('a', $levels->forScore(0)['key']);
        $this->assertSame('a', $levels->forScore(4)['key']);
        $this->assertSame('b', $levels->forScore(5)['key']);
        $this->assertSame('b', $levels->forScore(9)['key']);
        $this->assertSame('c', $levels->forScore(10)['key']);
        $this->assertSame('c', $levels->forScore(12)['key']);
    }

    #[Test]
    public function a_score_outside_every_level_falls_to_the_nearest_end(): void
    {
        $levels = Levels::fromArray([
            ['key' => 'a', 'label' => 'A', 'min' => 3, 'max' => 4],
            ['key' => 'b', 'label' => 'B', 'min' => 5, 'max' => 6],
        ]);

        $this->assertSame('a', $levels->forScore(-2)['key']);
        $this->assertSame('b', $levels->forScore(99)['key']);
        $this->assertNull(Levels::fromArray([])->forScore(1));
    }

    #[Test]
    public function overlapping_levels_are_refused(): void
    {
        $levels = Levels::fromArray([
            ['key' => 'a', 'label' => 'A', 'min' => 0, 'max' => 5],
            ['key' => 'b', 'label' => 'B', 'min' => 5, 'max' => 9],
        ]);

        $this->assertSame(['level_overlap'], $levels->problems());
    }

    #[Test]
    public function a_gap_between_levels_is_refused(): void
    {
        $levels = Levels::fromArray([
            ['key' => 'a', 'label' => 'A', 'min' => 0, 'max' => 4],
            ['key' => 'b', 'label' => 'B', 'min' => 6, 'max' => 9],
        ]);

        $this->assertSame(['level_gap'], $levels->problems());
    }

    #[Test]
    public function inverted_and_duplicate_and_unnamed_levels_are_refused(): void
    {
        $levels = Levels::fromArray([
            ['key' => 'a', 'label' => 'A', 'min' => 4, 'max' => 0],
            ['key' => 'a', 'label' => '', 'min' => 5, 'max' => 9],
        ]);

        $problems = $levels->problems();

        $this->assertContains('level_inverted', $problems);
        $this->assertContains('level_key_duplicate', $problems);
        $this->assertContains('level_incomplete', $problems);
    }

    #[Test]
    public function levels_of_a_published_assessment_must_cover_the_whole_range(): void
    {
        $levels = Levels::fromArray([
            ['key' => 'a', 'label' => 'A', 'min' => 2, 'max' => 8],
            ['key' => 'b', 'label' => 'B', 'min' => 9, 'max' => 14],
        ]);

        $this->assertSame([], $levels->problems());
        $this->assertSame(['level_range'], $levels->problems([2, 15]));
    }

    #[Test]
    public function the_facade_refuses_to_create_with_broken_levels_but_allows_a_draft_short_of_the_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // A gap is wrong in a draft too; only the range check waits for publishing.
        $this->makeAssessment(['published' => false, 'scoring' => [
            ['key' => 'a', 'label' => 'A', 'min' => 2, 'max' => 5],
            ['key' => 'b', 'label' => 'B', 'min' => 8, 'max' => 15],
        ]]);
    }

    #[Test]
    public function the_facade_refuses_a_scale_that_ends_where_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('scale');

        $this->makeAssessment(['published' => false, 'questions' => [
            ['text' => 'Wie sicher?', 'type' => 'scale', 'min' => 5, 'max' => 5, 'points_per_step' => 1],
        ]]);
    }

    #[Test]
    public function a_draft_may_leave_part_of_the_range_uncovered(): void
    {
        $assessment = $this->makeAssessment(['published' => false, 'scoring' => [
            ['key' => 'a', 'label' => 'A', 'min' => 2, 'max' => 5],
        ]]);

        $this->assertSame([], Assessments::levelProblems($assessment));

        $assessment->published = true;

        $this->assertSame(['level_range'], Assessments::levelProblems($assessment));
    }
}
