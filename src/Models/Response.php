<?php

namespace Goldnead\Assessments\Models;

use Goldnead\BrandContext\Concerns\HasBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One completed questionnaire.
 *
 * `answers` is keyed by question id: an option index for `single`, a list of
 * option indexes for `multi`, an integer for `scale`. The score and the level
 * are stored, not recomputed: a rule edited later must not rewrite what
 * somebody was told at the time.
 *
 * @property int $id
 * @property int $assessment_id
 * @property int $brand_id
 * @property string $email
 * @property string|null $name
 * @property array<int|string, mixed> $answers
 * @property int $score
 * @property string|null $result_key
 * @property int|null $contact_id
 * @property string $visit_token
 * @property Carbon|null $created_at
 */
class Response extends Model
{
    use HasBrand;

    public const UPDATED_AT = null;

    protected $table = 'assessment_responses';

    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    /** @return array{key: string, label: string, text: string|null, redirect: string|null}|null */
    public function level(): ?array
    {
        if ($this->result_key === null) {
            return null;
        }

        return $this->assessment?->levels()->byKey($this->result_key);
    }

    public function resultLabel(): ?string
    {
        return $this->level()['label'] ?? null;
    }
}
