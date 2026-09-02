<?php

namespace Goldnead\Assessments\Http\Controllers\Cp;

use Goldnead\Assessments\AssessmentsManager;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Statamic\CP\Column;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseController extends Controller
{
    public function __construct(protected AssessmentsManager $assessments) {}

    public function index(Request $request, int $assessment)
    {
        $this->authorizeOrFail($request, 'view assessment responses');

        $record = Assessment::query()->with('questions')->find($assessment);
        abort_if($record === null, 404);

        $levels = $record->levels();

        $rows = Response::query()
            ->where('assessment_id', $record->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Response $response) => [
                'id' => $response->id,
                'email' => $response->email,
                'name' => $response->name,
                'created_at' => $response->created_at?->toIso8601String(),
                'date' => $response->created_at?->isoFormat('L LT'),
                'score' => $response->score,
                'result_key' => $response->result_key,
                'result_label' => $response->result_key ? ($levels->byKey($response->result_key)['label'] ?? $response->result_key) : null,
                'result_url' => route('assessments.result', [$record->handle, $response->visit_token]),
            ])
            ->values()
            ->all();

        return Inertia::render('assessments::Assessments/Responses', [
            'assessment' => [
                'id' => $record->id,
                'title' => $record->title,
                'handle' => $record->handle,
                'total' => Response::query()->where('assessment_id', $record->id)->count(),
            ],
            'responses' => $rows,
            'columns' => [
                Column::make('email')->label(__('assessments::messages.column_email')),
                Column::make('name')->label(__('assessments::messages.column_name')),
                Column::make('date')->label(__('assessments::messages.column_date')),
                Column::make('score')->label(__('assessments::messages.column_score'))->numeric(true),
                Column::make('result_label')->label(__('assessments::messages.column_level')),
            ],
            'exportUrl' => cp_route('assessments.responses.export', $record->id),
            'editUrl' => cp_route('assessments.edit', $record->id),
            'indexUrl' => cp_route('assessments.index'),
            'canEdit' => $this->userCan($request, 'edit assessments'),
        ]);
    }

    /**
     * Every response as CSV, one column per question, answers as their labels.
     *
     * Streamed, so a long-running assessment does not build a string of
     * every row before the first byte goes out. A UTF-8 byte-order mark up
     * front, because the file is opened in Excel more often than anywhere
     * else and Excel guesses the encoding wrong without it.
     */
    public function export(Request $request, int $assessment): StreamedResponse
    {
        $this->authorizeOrFail($request, 'view assessment responses');

        $record = Assessment::query()->with('questions')->find($assessment);
        abort_if($record === null, 404);

        $filename = Str::slug($record->handle).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($record) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_merge([
                __('assessments::messages.column_email'),
                __('assessments::messages.column_name'),
                __('assessments::messages.column_date'),
                __('assessments::messages.column_score'),
                __('assessments::messages.column_level_key'),
                __('assessments::messages.column_level'),
            ], $record->questions->pluck('text')->all()), ';', '"', '\\');

            $levels = $record->levels();

            Response::query()
                ->where('assessment_id', $record->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->chunk(200, function ($responses) use ($out, $record, $levels) {
                    foreach ($responses as $response) {
                        $response->setRelation('assessment', $record);

                        $answers = array_column($this->assessments->readableAnswers($response), 'answer');

                        fputcsv($out, array_map([self::class, 'cell'], array_merge([
                            $response->email,
                            $response->name,
                            $response->created_at?->toDateTimeString(),
                            $response->score,
                            $response->result_key,
                            $response->result_key ? ($levels->byKey($response->result_key)['label'] ?? '') : '',
                        ], $answers)), ';', '"', '\\');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * A cell a spreadsheet will not execute.
     *
     * Every value here was typed by a stranger — a name, an option label —
     * and the file is opened in Excel. A cell beginning with `=`, `+`, `-`
     * or `@` is a formula there, and `=HYPERLINK(...)` in a name is a phishing
     * link in the editor's spreadsheet. A leading apostrophe makes it text;
     * a tab or carriage return up front is the same trick in another coat.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
