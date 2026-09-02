<?php

namespace Goldnead\Assessments\Http\Controllers\Cp;

use Goldnead\Assessments\AssessmentsManager;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Question;
use Goldnead\Assessments\Scoring\Levels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;
use Statamic\CP\Column;
use Statamic\Support\Str;

class AssessmentController extends Controller
{
    public function __construct(protected AssessmentsManager $assessments) {}

    public function index(Request $request)
    {
        $this->authorizeOrFail($request, 'view assessments');

        $rows = Assessment::query()
            ->with('brand')
            ->withCount(['questions', 'responses'])
            ->orderBy('title')
            ->get()
            ->map(fn (Assessment $assessment) => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'handle' => $assessment->handle,
                'questions' => $assessment->questions_count,
                'responses' => $assessment->responses_count,
                'published' => $assessment->published,
                'brand' => $assessment->brand?->name,
                'edit_url' => cp_route('assessments.edit', $assessment->id),
                'responses_url' => cp_route('assessments.responses.index', $assessment->id),
                'public_url' => route('assessments.show', $assessment->handle),
                'delete_url' => cp_route('assessments.destroy', $assessment->id),
            ])
            ->values()
            ->all();

        return Inertia::render('assessments::Assessments/Index', [
            'assessments' => $rows,
            'columns' => $this->columns(),
            'createUrl' => cp_route('assessments.create'),
            'canEdit' => $this->userCan($request, 'edit assessments'),
            'canViewResponses' => $this->userCan($request, 'view assessment responses'),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeOrFail($request, 'edit assessments');

        return Inertia::render('assessments::Assessments/Edit', [
            'assessment' => null,
            'storeUrl' => cp_route('assessments.store'),
            'indexUrl' => cp_route('assessments.index'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeOrFail($request, 'edit assessments');

        $data = $this->validated($request, null);

        $handle = ($data['handle'] ?? null) ?: Str::slug($data['title'], '_');

        if (Assessment::query()->acrossBrands()->where('handle', $handle)->exists()) {
            return back()->withErrors(['handle' => __('assessments::messages.handle_taken')]);
        }

        try {
            $assessment = $this->assessments->create($this->attributes($data) + ['handle' => $handle]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['scoring' => $e->getMessage()]);
        }

        return redirect()
            ->to(cp_route('assessments.edit', $assessment->id))
            ->with('success', __('assessments::messages.created'));
    }

    public function edit(Request $request, int $assessment)
    {
        $this->authorizeOrFail($request, 'edit assessments');

        $record = Assessment::query()->with('questions')->withCount('responses')->find($assessment);
        abort_if($record === null, 404);

        return Inertia::render('assessments::Assessments/Edit', [
            'assessment' => $this->payload($record),
            'updateUrl' => cp_route('assessments.update', $record->id),
            'deleteUrl' => cp_route('assessments.destroy', $record->id),
            'indexUrl' => cp_route('assessments.index'),
            'responsesUrl' => cp_route('assessments.responses.index', $record->id),
            'publicUrl' => route('assessments.show', $record->handle),
            'canViewResponses' => $this->userCan($request, 'view assessment responses'),
        ]);
    }

    public function update(Request $request, int $assessment)
    {
        $this->authorizeOrFail($request, 'edit assessments');

        $record = Assessment::query()->with('questions')->find($assessment);
        abort_if($record === null, 404);

        $data = $this->validated($request, $record);

        try {
            $this->assessments->update($record, $this->attributes($data));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['scoring' => $e->getMessage()]);
        }

        return back()->with('success', __('assessments::messages.saved'));
    }

    public function destroy(Request $request, int $assessment)
    {
        $this->authorizeOrFail($request, 'edit assessments');

        $record = Assessment::query()->find($assessment);
        abort_if($record === null, 404);

        $record->delete();

        return redirect()
            ->to(cp_route('assessments.index'))
            ->with('success', __('assessments::messages.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Assessment $existing): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:191'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'outro' => ['nullable', 'string', 'max:20000'],
            'published' => ['boolean'],
            'collect' => ['nullable', 'array'],
            'collect.name' => ['nullable', Rule::in(['off', 'optional', 'required'])],
            'questions' => ['array'],
            // The id of an existing question, so a save keeps it — and with
            // it the key every stored response uses. New questions send none.
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.text' => ['required', 'string', 'max:2000'],
            'questions.*.help' => ['nullable', 'string', 'max:2000'],
            'questions.*.type' => ['required', Rule::in(Question::TYPES)],
            'questions.*.options' => ['array'],
            'questions.*.options.*.label' => ['required', 'string', 'max:500'],
            'questions.*.options.*.points' => ['required', 'integer', 'between:-1000,1000'],
            'questions.*.min' => ['nullable', 'integer', 'between:-1000,1000'],
            'questions.*.max' => ['nullable', 'integer', 'between:-1000,1000'],
            'questions.*.points_per_step' => ['nullable', 'integer', 'between:-1000,1000'],
            'scoring' => ['array'],
            'scoring.*.key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'scoring.*.label' => ['required', 'string', 'max:191'],
            'scoring.*.min' => ['required', 'integer'],
            'scoring.*.max' => ['required', 'integer'],
            'scoring.*.text' => ['nullable', 'string', 'max:20000'],
            'scoring.*.redirect' => ['nullable', 'url', 'max:2000'],
        ];

        if ($existing === null) {
            $rules['handle'] = ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'];
        }

        $validator = Validator::make($request->all(), $rules, [], [
            'questions.*.text' => __('assessments::messages.field_question_text'),
            'questions.*.options.*.label' => __('assessments::messages.field_option_label'),
            'questions.*.options.*.points' => __('assessments::messages.field_option_points'),
            'scoring.*.key' => __('assessments::messages.field_level_key'),
            'scoring.*.label' => __('assessments::messages.field_level_label'),
        ]);

        // The per-question rules that depend on the type, and the level rules
        // that depend on each other: neither fits a flat rule list.
        $validator->after(function ($validator) use ($request) {
            foreach ((array) $request->input('questions', []) as $index => $question) {
                $type = $question['type'] ?? null;

                if ($type === Question::TYPE_SCALE) {
                    if ((int) ($question['min'] ?? 0) >= (int) ($question['max'] ?? 0)) {
                        $validator->errors()->add("questions.$index.max", __('assessments::messages.scale_range_invalid'));
                    }
                } elseif (count((array) ($question['options'] ?? [])) < 2) {
                    $validator->errors()->add("questions.$index.options", __('assessments::messages.options_min'));
                }
            }

            $levels = Levels::fromArray((array) $request->input('scoring', []));
            $published = (bool) $request->boolean('published');

            $range = null;

            if ($published) {
                $probe = new Assessment;
                $probe->setRelation('questions', collect((array) $request->input('questions', []))
                    ->map(fn ($q) => new Question([
                        'type' => $q['type'] ?? Question::TYPE_SINGLE,
                        'options' => $q['options'] ?? [],
                        'min' => $q['min'] ?? 0,
                        'max' => $q['max'] ?? 0,
                        'points_per_step' => $q['points_per_step'] ?? 0,
                    ])));
                $range = $probe->scoreRange();

                if ($levels->isEmpty()) {
                    $validator->errors()->add('scoring', __('assessments::messages.levels_required_to_publish'));
                }

                if (count((array) $request->input('questions', [])) === 0) {
                    $validator->errors()->add('questions', __('assessments::messages.questions_required_to_publish'));
                }
            }

            foreach ($levels->problems($range) as $problem) {
                $validator->errors()->add('scoring', __('assessments::messages.'.$problem, [
                    'min' => $range[0] ?? 0,
                    'max' => $range[1] ?? 0,
                ]));
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'intro' => ($data['intro'] ?? null) ?: null,
            'outro' => ($data['outro'] ?? null) ?: null,
            'published' => (bool) ($data['published'] ?? false),
            'collect' => ['name' => $data['collect']['name'] ?? 'optional'],
            'scoring' => Levels::fromArray($data['scoring'] ?? [])->all(),
            'questions' => $data['questions'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'handle' => $assessment->handle,
            'title' => $assessment->title,
            'intro' => $assessment->intro,
            'outro' => $assessment->outro,
            'published' => $assessment->published,
            'collect' => ['name' => $assessment->nameMode()],
            'scoring' => $assessment->levels()->all(),
            'questions' => $assessment->questions->map(fn (Question $question) => [
                'id' => $question->id,
                'text' => $question->text,
                'help' => $question->help,
                'type' => $question->type,
                'options' => $question->optionList(),
                'min' => $question->min,
                'max' => $question->max,
                'points_per_step' => $question->points_per_step,
            ])->values()->all(),
            'responses_count' => $assessment->responses_count ?? 0,
        ];
    }

    /** @return list<Column> */
    protected function columns(): array
    {
        return [
            Column::make('title')->label(__('assessments::messages.column_title')),
            Column::make('handle')->label(__('assessments::messages.column_handle')),
            Column::make('questions')->label(__('assessments::messages.column_questions'))->numeric(true),
            Column::make('responses')->label(__('assessments::messages.column_responses'))->numeric(true),
            Column::make('published')->label(__('assessments::messages.column_published')),
            Column::make('brand')->label(__('assessments::messages.column_brand')),
        ];
    }
}
