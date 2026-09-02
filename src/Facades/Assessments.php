<?php

namespace Goldnead\Assessments\Facades;

use Goldnead\Assessments\AssessmentsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goldnead\Assessments\Models\Assessment|null find(string $handle)
 * @method static \Goldnead\Assessments\Models\Assessment create(array $attributes)
 * @method static \Goldnead\Assessments\Models\Assessment update(\Goldnead\Assessments\Models\Assessment $assessment, array $attributes)
 * @method static list<string> levelProblems(\Goldnead\Assessments\Models\Assessment $assessment)
 * @method static array{score: int, breakdown: array<int, int>} score(\Goldnead\Assessments\Models\Assessment $assessment, array $answers)
 * @method static \Goldnead\Assessments\Models\Response submit(\Goldnead\Assessments\Models\Assessment $assessment, string $email, ?string $name, array $answers, ?string $visitToken = null)
 * @method static list<array{question: string, type: string, answer: string, points: int}> readableAnswers(\Goldnead\Assessments\Models\Response $response)
 *
 * @see AssessmentsManager
 */
class Assessments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AssessmentsManager::class;
    }
}
