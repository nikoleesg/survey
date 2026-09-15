<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSample
{
    public function sample(): BelongsTo
    {
        return $this->belongsTo(config('survey.sample_model'), 'sample_id');
    }

    public function scopeOfSample(Builder $query, int $sampleId): void
    {
        $query->where('sample_id', $sampleId);
    }

    public function scopeOfSurvey(Builder $query, ?string $surveyId = null): void
    {
        $query->whereHas('sample', fn (Builder $sample) => $sample->ofSurvey($surveyId));
    }
}
