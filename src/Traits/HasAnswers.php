<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nikoleesg\Survey\Models\Answer;
use Awobaz\Compoships\Compoships;

trait HasAnswers
{
    use Compoships;

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, ['interview_number', 'survey_id'], ['interview_id', 'survey_id']);
    }

    /**
     * get survey sample answers
     * @return array
     */
    public function getAnswers(): array
    {
        $answerRelation = $this->relationLoaded('answers') ? $this->answers : $this->answers();

        $answers = [];

        $answerRelation->pluck('result')
            ->each(function ($item) use (&$answers) {
                $answers = array_merge($answers, $item);
            });

        return $answers;
    }

}
