<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasAnswers
{
    public function answers(): HasMany
    {
        return $this->hasMany(config('survey.closed_answer_model'), 'sample_id');
    }

    /**
     * All of the sample's answers merged into one array keyed by variable slug.
     */
    public function getAnswers(): array
    {
        $answerRelation = $this->relationLoaded('answers') ? $this->answers : $this->answers();

        $answers = [];

        $answerRelation->pluck('result')
            ->each(function ($item) use (&$answers) {
                if (is_array($item)) {
                    $answers = array_merge($answers, $item);
                }
            });

        return $answers;
    }
}
