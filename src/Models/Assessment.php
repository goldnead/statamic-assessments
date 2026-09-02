<?php

namespace Goldnead\Assessments\Models;

use Goldnead\Assessments\Scoring\Levels;
use Goldnead\BrandContext\Concerns\HasBrand;
use Goldnead\BrandContext\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A questionnaire with a scoring rule.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $handle
 * @property string $title
 * @property string|null $intro
 * @property string|null $outro
 * @property bool $published
 * @property array<string, mixed>|null $collect
 * @property list<array<string, mixed>>|null $scoring
 * @property array<string, mixed>|null $meta
 * @property-read Brand|null $brand
 * @property-read int|null $questions_count
 * @property-read int|null $responses_count
 */
class Assessment extends Model
{
    use HasBrand;

    protected $table = 'assessments';

    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
        'collect' => 'array',
        'scoring' => 'array',
        'meta' => 'array',
    ];

    /**
     * The questions and responses go with the assessment. Done here rather
     * than left to the foreign keys: SQLite enforces them only when asked to,
     * and a response without its assessment is a row nothing can read.
     */
    protected static function booted(): void
    {
        static::deleting(function (Assessment $assessment): void {
            $assessment->responses()->delete();
            $assessment->questions()->delete();
        });
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'assessment_id')->orderBy('position');
    }

    /** @return HasMany<Response, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'assessment_id');
    }

    /**
     * How the name field is asked for: `off`, `optional` or `required`.
     *
     * The address is always required — without it there is no contact and
     * no way to send the result on, which is the whole point of asking.
     */
    public function nameMode(): string
    {
        $mode = (string) data_get($this->collect, 'name', 'optional');

        return in_array($mode, ['off', 'optional', 'required'], true) ? $mode : 'optional';
    }

    public function levels(): Levels
    {
        return Levels::fromArray($this->scoring ?? []);
    }

    /**
     * The lowest and highest score the questions can produce.
     *
     * @return array{0: int, 1: int}
     */
    public function scoreRange(): array
    {
        $min = 0;
        $max = 0;

        foreach ($this->questions as $question) {
            [$questionMin, $questionMax] = $question->pointRange();
            $min += $questionMin;
            $max += $questionMax;
        }

        return [$min, $max];
    }
}
