<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LaraUtil\Foundation\Traits\HasUuid;
use Nikoleesg\Survey\Traits\HasTablePrefix;

class Paradata extends Model
{
    use HasUuid, HasTablePrefix;

    protected $guarded = [];

    public function scopeSurvey(Builder $query, string $surveyId): void
    {
        $query->where('survey_id', $surveyId);
    }

    public function scopeSample(Builder $query, int $interviewNumber): void
    {
        $query->where('interview_number', $interviewNumber);
    }
}
