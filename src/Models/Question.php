<?php

namespace Goldnead\Assessments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question. Three kinds:
 *
 * - `single`: one option, the option's points.
 * - `multi`: any number of options, their points summed.
 * - `scale`: a number between `min` and `max`, times `points_per_step`.
 *
 * Not brand-scoped itself: it belongs to an assessment, and the assessment is.
 *
 * @property int $id
 * @property int $assessment_id
 * @property int $position
 * @property string $text
 * @property string|null $help
 * @property string $type
 * @property list<array{label: string, points: int}>|null $options
 * @property int|null $min
 * @property int|null $max
 * @property int|null $points_per_step
 */
class Question extends Model
{
    public const TYPE_SINGLE = 'single';

    public const TYPE_MULTI = 'multi';

    public const TYPE_SCALE = 'scale';

    public const TYPES = [self::TYPE_SINGLE, self::TYPE_MULTI, self::TYPE_SCALE];

    protected $table = 'assessment_questions';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'options' => 'array',
        'min' => 'integer',
        'max' => 'integer',
        'points_per_step' => 'integer',
    ];

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    /** @return list<array{label: string, points: int}> */
    public function optionList(): array
    {
        $options = [];

        foreach ((array) ($this->options ?? []) as $option) {
            if (! is_array($option)) {
                continue;
            }

            $options[] = [
                'label' => (string) ($option['label'] ?? ''),
                'points' => (int) ($option['points'] ?? 0),
            ];
        }

        return $options;
    }

    /**
     * The fewest and the most points an answer to this question can earn.
     *
     * Multi counts negatives towards the minimum and positives towards the
     * maximum, because a visitor may tick any subset. Scale runs from
     * `min × step` to `max × step`, in whichever order a negative step puts
     * them.
     *
     * @return array{0: int, 1: int}
     */
    public function pointRange(): array
    {
        $points = array_column($this->optionList(), 'points');

        return match ($this->type) {
            self::TYPE_MULTI => [
                (int) array_sum(array_filter($points, fn (int $p) => $p < 0)),
                (int) array_sum(array_filter($points, fn (int $p) => $p > 0)),
            ],
            self::TYPE_SCALE => [
                min((int) $this->min * (int) $this->points_per_step, (int) $this->max * (int) $this->points_per_step),
                max((int) $this->min * (int) $this->points_per_step, (int) $this->max * (int) $this->points_per_step),
            ],
            default => $points === [] ? [0, 0] : [min($points), max($points)],
        };
    }
}
