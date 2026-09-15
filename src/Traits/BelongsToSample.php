<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nikoleesg\Survey\Models\Sample;

trait BelongsToSample
{
    /**
     * @return BelongsTo<Sample, $this>
     */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::modelClass(), 'sample_id');
    }

    public function scopeOfSample(Builder $query, int $sampleId): void
    {
        $query->where('sample_id', $sampleId);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeOfSurvey(Builder $query, ?string $surveyId = null): void
    {
        $query->whereHas('sample', fn (Builder $sample) => $sample->ofSurvey($surveyId));
    }
}
