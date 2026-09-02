<?php

namespace Goldnead\Assessments\Http\Controllers\Web;

use Goldnead\Assessments\AssessmentsManager;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The form endpoint. Opened by strangers, so it validates against the
 * questions as they are now and says as little as possible.
 */
class SubmitController
{
    public function __construct(protected AssessmentsManager $assessments) {}

    public function __invoke(Request $request, string $handle)
    {
        $assessment = $this->assessments->find($handle);

        abort_unless($assessment !== null && $assessment->published, 404);

        // A filled honeypot gets a believable answer and nothing else.
        if ($request->filled('website')) {
            return redirect()->to(route('assessments.show', $assessment->handle));
        }

        $data = $request->validate($this->rules($assessment), [], $this->attributes($assessment));

        $response = $this->assessments->submit(
            $assessment,
            $data['email'],
            $data['name'] ?? null,
            $data['answers'] ?? [],
            $this->visitToken($request),
        );

        $level = $response->level();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'score' => $response->score,
                    'result_key' => $response->result_key,
                    'result_label' => $level['label'] ?? null,
                    'result_url' => route('assessments.result', [$assessment->handle, $response->visit_token]),
                    'redirect' => $level['redirect'] ?? null,
                ],
            ]);
        }

        if (! empty($level['redirect'])) {
            return redirect()->away($level['redirect']);
        }

        return redirect()->to(route('assessments.result', [$assessment->handle, $response->visit_token]));
    }

    /**
     * One rule set per question, from the question itself. Every question is
     * required: a skipped one would silently count as zero and the level
     * would say something the visitor did not answer for.
     *
     * @return array<string, mixed>
     */
    protected function rules(Assessment $assessment): array
    {
        $rules = [
            'email' => ['required', 'email', 'max:191'],
            'name' => [$assessment->nameMode() === 'required' ? 'required' : 'nullable', 'string', 'max:191'],
            'answers' => ['required', 'array'],
        ];

        foreach ($assessment->questions as $question) {
            $key = 'answers.'.$question->id;
            $last = max(0, count($question->optionList()) - 1);

            match ($question->type) {
                Question::TYPE_SINGLE => $rules[$key] = ['required', 'integer', 'between:0,'.$last],
                Question::TYPE_MULTI => [
                    $rules[$key] = ['required', 'array', 'min:1'],
                    $rules[$key.'.*'] = ['integer', 'between:0,'.$last, 'distinct'],
                ],
                Question::TYPE_SCALE => $rules[$key] = ['required', 'integer', 'between:'.(int) $question->min.','.(int) $question->max],
                default => null,
            };
        }

        return $rules;
    }

    /**
     * So a validation message names the question, not `answers.14`.
     *
     * @return array<string, string>
     */
    protected function attributes(Assessment $assessment): array
    {
        $attributes = [
            'email' => __('assessments::messages.field_email'),
            'name' => __('assessments::messages.field_name'),
        ];

        foreach ($assessment->questions as $question) {
            $attributes['answers.'.$question->id] = Str::limit($question->text, 60);
            $attributes['answers.'.$question->id.'.*'] = Str::limit($question->text, 60);
        }

        return $attributes;
    }

    /**
     * The token the page was rendered with, if it still looks like one. It
     * only has to be unique; a browser sending something odd gets a fresh one.
     */
    protected function visitToken(Request $request): ?string
    {
        $token = (string) $request->input('_visit', '');

        return preg_match('/^[A-Za-z0-9]{20,64}$/', $token) ? $token : null;
    }
}
