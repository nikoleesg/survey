<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOpenAnswers
{
    public function openAnswers(): HasMany
    {
        return $this->hasMany(config('survey.open_answer_model'), 'sample_id');
    }
}
