<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models that carry a survey_id scoping column (samples, variables).
 * A null survey id anywhere in the package means "the configured survey".
 */
trait BelongsToSurvey
{
    public static function bootBelongsToSurvey(): void
    {
        static::creating(function ($model) {
            $model->survey_id = static::resolveSurveyId($model->survey_id);
        });
    }

    public static function resolveSurveyId(?string $surveyId): string
    {
        return $surveyId ?? config('survey.survey_id');
    }

    public function scopeOfSurvey(Builder $query, ?string $surveyId = null): void
    {
        $query->where('survey_id', static::resolveSurveyId($surveyId));
    }
}
