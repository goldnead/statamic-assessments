<?php

namespace Goldnead\Assessments\Support;

use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Question;
use Goldnead\Assessments\Models\Response;
use Illuminate\Contracts\View\View as ViewContract;
use Statamic\View\View;

/**
 * The public pages: what a template gets, and how it is wrapped.
 *
 * Templates are Antlers and rendered through Statamic's own `View`, so the
 * cascade (`site`, `csrf_field`, the globals) is there and the site's layout
 * wraps the page. When the site has no layout of that name the addon's own
 * plain shell is used — a fresh install sees a working page before anybody
 * has written one.
 */
class Page
{
    /**
     * Everything the form template needs, under one key and flat.
     *
     * @return array<string, mixed>
     */
    public static function formContext(Assessment $assessment, string $visitToken, bool $preview = false): array
    {
        // What the visitor had chosen when a validation error sent them back,
        // resolved here so the template compares nothing itself: Antlers'
        // loose `==` would read a missing answer as option 0 and pre-tick it.
        $old = (array) old('answers', []);

        $chosen = function (Question $question, int|string $value) use ($old): bool {
            $answer = $old[(string) $question->id] ?? null;

            if (is_array($answer)) {
                return in_array((string) $value, array_map('strval', $answer), true);
            }

            return $answer !== null && (string) $answer === (string) $value;
        };

        return [
            'handle' => $assessment->handle,
            'title' => $assessment->title,
            'intro' => $assessment->intro,
            'outro' => $assessment->outro,
            'name_mode' => $assessment->nameMode(),
            'ask_name' => $assessment->nameMode() !== 'off',
            'name_required' => $assessment->nameMode() === 'required',
            'questions' => $assessment->questions->map(fn (Question $question) => [
                'id' => $question->id,
                'field' => 'answers['.$question->id.']',
                'text' => $question->text,
                'help' => $question->help,
                'type' => $question->type,
                'is_single' => $question->type === 'single',
                'is_multi' => $question->type === 'multi',
                'is_scale' => $question->type === 'scale',
                'options' => collect($question->optionList())->map(fn ($option, $index) => [
                    'index' => $index,
                    'label' => $option['label'],
                    'checked' => $chosen($question, $index),
                ])->values()->all(),
                'min' => $question->min,
                'max' => $question->max,
                'steps' => $question->type === 'scale' && $question->max >= $question->min
                    ? collect(range((int) $question->min, (int) $question->max))->map(fn ($value) => [
                        'value' => $value,
                        'checked' => $chosen($question, $value),
                    ])->all()
                    : [],
            ])->values()->all(),
            'action' => route('assessments.submit', $assessment->handle),
            'url' => route('assessments.show', $assessment->handle),
            'visit_token' => $visitToken,
            'preview' => $preview,
            'styles' => config('assessments.styles', true),
        ];
    }

    /**
     * @param  list<array{question: string, type: string, answer: string, points: int}>  $answers
     * @return array<string, mixed>
     */
    public static function resultContext(Response $response, array $answers): array
    {
        $assessment = $response->assessment;
        $level = $response->level();

        return [
            'handle' => $assessment->handle,
            'title' => $assessment->title,
            'outro' => $assessment->outro,
            'email' => $response->email,
            'name' => $response->name,
            'score' => $response->score,
            'result_key' => $level['key'] ?? null,
            'result_label' => $level['label'] ?? null,
            'result_text' => $level['text'] ?? null,
            'answers' => $answers,
            // So a published copy of the result template can send the visitor
            // on to a page of the site's own with `?r={{ token }}`, where
            // `{{ assessments:result }}` shows the same result.
            'token' => $response->visit_token,
            'url' => route('assessments.show', $assessment->handle),
            'styles' => config('assessments.styles', true),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function render(string $template, array $context): ViewContract|View
    {
        $layout = (string) config('assessments.layout', 'layout');

        return View::make($template, ['assessment' => $context] + $context)
            ->layout(view()->exists($layout) ? $layout : 'assessments::layout');
    }
}
