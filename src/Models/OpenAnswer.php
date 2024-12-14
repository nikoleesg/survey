<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Traits\HasTablePrefix;

class OpenAnswer extends Model
{
    use HasTablePrefix;

    protected $guarded = [];

    public function scopeOfSurvey(Builder $query, string $surveyId): void
    {
        $query->where('survey_id', $surveyId);
    }

    public function scopeOfSample(Builder $query, int $interviewNumber): void
    {
        $query->where('interview_number', $interviewNumber);
    }
}
