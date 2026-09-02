<?php

namespace Goldnead\Assessments;

use Goldnead\Assessments\Events\AssessmentCompleted;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Question;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Scoring\Levels;
use Goldnead\Assessments\Scoring\Scorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The facade root. Everything the Control Panel, the public routes and a
 * site's own code do with an assessment goes through here, so the scoring
 * and the level rules have exactly one reading.
 */
class AssessmentsManager
{
    public function __construct(protected Scorer $scorer) {}

    public function find(string $handle): ?Assessment
    {
        return Assessment::query()->with('questions')->where('handle', $handle)->first();
    }

    /**
     * Create an assessment with its questions in one go.
     *
     * `questions` is a list of arrays with `text`, `type` and, per type,
     * `options` (`[{label, points}]`) or `min`/`max`/`points_per_step`.
     * `scoring` is the list of levels. The levels are checked for overlap and
     * gaps; a caller that wants a draft with half-built levels leaves
     * `published` off and gets the same check only when it publishes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Assessment
    {
        $questions = $attributes['questions'] ?? [];
        unset($attributes['questions']);

        $attributes['handle'] = $attributes['handle'] ?? Str::slug((string) ($attributes['title'] ?? ''), '_');

        return DB::transaction(function () use ($attributes, $questions) {
            $assessment = Assessment::query()->create($attributes);

            $this->replaceQuestions($assessment, $questions);
            $this->guardLevels($assessment);

            return $assessment;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Assessment $assessment, array $attributes): Assessment
    {
        $questions = $attributes['questions'] ?? null;
        unset($attributes['questions'], $attributes['handle']);

        return DB::transaction(function () use ($assessment, $attributes, $questions) {
            $assessment->fill($attributes)->save();

            if ($questions !== null) {
                $this->replaceQuestions($assessment, $questions);
            }

            $this->guardLevels($assessment);

            return $assessment;
        });
    }

    /**
     * What is wrong with the levels of an assessment, as message keys.
     *
     * The achievable range is enforced only for a published assessment.
     *
     * @return list<string>
     */
    public function levelProblems(Assessment $assessment): array
    {
        $assessment->loadMissing('questions');

        return $assessment->levels()->problems($assessment->published ? $assessment->scoreRange() : null);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array{score: int, breakdown: array<int, int>}
     */
    public function score(Assessment $assessment, array $answers): array
    {
        $assessment->loadMissing('questions');

        return $this->scorer->score($assessment, $answers);
    }

    /**
     * Store a completed questionnaire and say so.
     *
     * The answers are expected validated — the controller does that against
     * the questions — and are stored as given, keyed by question id, so a
     * response can be read back without the scorer.
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function submit(Assessment $assessment, string $email, ?string $name, array $answers, ?string $visitToken = null): Response
    {
        $assessment->loadMissing('questions');

        $answers = $this->normalizeAnswers($assessment, $answers);
        $result = $this->scorer->score($assessment, $answers);
        $level = $assessment->levels()->forScore($result['score']);

        $response = $assessment->responses()->create([
            'brand_id' => $assessment->brand_id,
            'email' => mb_strtolower(trim($email)),
            'name' => $name !== null && trim($name) !== '' ? trim($name) : null,
            'answers' => $answers,
            'score' => $result['score'],
            'result_key' => $level['key'] ?? null,
            'visit_token' => $visitToken ?: Str::random(40),
            'created_at' => now(),
        ]);

        $response->setRelation('assessment', $assessment);

        AssessmentCompleted::dispatch($response);

        return $response;
    }

    /**
     * The answers of a response, readable: the question, the chosen labels,
     * the points. For the result page, the CSV and the contact timeline.
     *
     * @return list<array{question: string, type: string, answer: string, points: int}>
     */
    public function readableAnswers(Response $response): array
    {
        $assessment = $response->assessment;
        $assessment->loadMissing('questions');

        $rows = [];

        // PHP normalises numeric string keys to integers, so one lookup by
        // the string form finds the key however it was stored.
        $answers = $response->answers ?? [];

        foreach ($assessment->questions as $question) {
            $answer = $answers[(string) $question->id] ?? null;
            $options = $question->optionList();

            $label = match ($question->type) {
                Question::TYPE_SINGLE => is_numeric($answer) ? ($options[(int) $answer]['label'] ?? '') : '',
                Question::TYPE_MULTI => implode(', ', array_map(
                    fn ($index) => is_numeric($index) ? ($options[(int) $index]['label'] ?? '') : '',
                    is_array($answer) ? $answer : []
                )),
                default => $answer === null ? '' : (string) $answer,
            };

            $rows[] = [
                'question' => $question->text,
                'type' => $question->type,
                'answer' => $label,
                'points' => $this->scorer->pointsFor($question, $answer),
            ];
        }

        return $rows;
    }

    /**
     * Rewrite the questions of an assessment from a list. Positions follow
     * list order; ids are not preserved, which is fine because a response
     * stores what it was answered against by id and an edited question is a
     * different question.
     *
     * @param  iterable<mixed>  $questions
     */
    protected function replaceQuestions(Assessment $assessment, iterable $questions): void
    {
        $assessment->questions()->delete();

        $position = 0;

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $type = (string) ($question['type'] ?? Question::TYPE_SINGLE);

            if (! in_array($type, Question::TYPES, true)) {
                throw new InvalidArgumentException("Unknown question type [{$type}].");
            }

            $assessment->questions()->create([
                'position' => $position++,
                'text' => (string) ($question['text'] ?? ''),
                'help' => isset($question['help']) && $question['help'] !== '' ? (string) $question['help'] : null,
                'type' => $type,
                'options' => $type === Question::TYPE_SCALE ? null : array_values(array_map(
                    fn ($option) => [
                        'label' => (string) (is_array($option) ? ($option['label'] ?? '') : $option),
                        'points' => (int) (is_array($option) ? ($option['points'] ?? 0) : 0),
                    ],
                    (array) ($question['options'] ?? [])
                )),
                'min' => $type === Question::TYPE_SCALE ? (int) ($question['min'] ?? 0) : null,
                'max' => $type === Question::TYPE_SCALE ? (int) ($question['max'] ?? 10) : null,
                'points_per_step' => $type === Question::TYPE_SCALE ? (int) ($question['points_per_step'] ?? 1) : null,
            ]);
        }

        $assessment->unsetRelation('questions');
    }

    protected function guardLevels(Assessment $assessment): void
    {
        $problems = $this->levelProblems($assessment);

        if ($problems !== []) {
            throw new InvalidArgumentException('The result levels are not valid: '.implode(', ', $problems));
        }
    }

    /**
     * Keep only answers to questions that exist, in the shape the type wants.
     *
     * @param  array<int|string, mixed>  $answers
     * @return array<int|string, mixed>
     */
    protected function normalizeAnswers(Assessment $assessment, array $answers): array
    {
        $clean = [];

        foreach ($assessment->questions as $question) {
            $answer = $answers[(string) $question->id] ?? null;

            $clean[(string) $question->id] = match ($question->type) {
                Question::TYPE_MULTI => array_values(array_map('intval', array_filter(
                    is_array($answer) ? $answer : [],
                    fn ($v) => is_numeric($v)
                ))),
                default => is_numeric($answer) ? (int) $answer : null,
            };
        }

        return $clean;
    }

    public function levelsOf(Assessment $assessment): Levels
    {
        return $assessment->levels();
    }
}
