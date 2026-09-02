<?php

namespace Goldnead\Assessments\Scoring;

use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Question;

/**
 * Turns answers into points.
 *
 * Deliberately dumb: one method per question type, no weighting, no partial
 * credit rules. Everything an editor can express is in the options and the
 * levels; this class only adds them up.
 */
class Scorer
{
    /**
     * Points for one question.
     *
     * The answer arrives as the browser sent it — a string index, a list of
     * string indexes, a numeric string — and is coerced here, once. Anything
     * that does not fit the question earns nothing rather than throwing: the
     * request was validated before this ran, so an unfit value here is a
     * question edited between page load and submit.
     */
    public function pointsFor(Question $question, mixed $answer): int
    {
        $options = $question->optionList();

        return match ($question->type) {
            Question::TYPE_SINGLE => $this->optionPoints($options, $answer),
            Question::TYPE_MULTI => array_sum(array_map(
                fn ($index) => $this->optionPoints($options, $index),
                array_values(array_unique(is_array($answer) ? $answer : []))
            )),
            Question::TYPE_SCALE => $this->scalePoints($question, $answer),
            default => 0,
        };
    }

    /**
     * The total, and the points per question.
     *
     * @param  array<int|string, mixed>  $answers  keyed by question id
     * @return array{score: int, breakdown: array<int, int>}
     */
    public function score(Assessment $assessment, array $answers): array
    {
        $score = 0;
        $breakdown = [];

        foreach ($assessment->questions as $question) {
            $points = $this->pointsFor($question, $answers[(string) $question->id] ?? null);
            $breakdown[$question->id] = $points;
            $score += $points;
        }

        return ['score' => $score, 'breakdown' => $breakdown];
    }

    /** @param  list<array{label: string, points: int}>  $options */
    protected function optionPoints(array $options, mixed $index): int
    {
        if (! is_numeric($index)) {
            return 0;
        }

        return $options[(int) $index]['points'] ?? 0;
    }

    protected function scalePoints(Question $question, mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $value = (int) $value;

        if ($value < (int) $question->min || $value > (int) $question->max) {
            return 0;
        }

        return $value * (int) $question->points_per_step;
    }
}
