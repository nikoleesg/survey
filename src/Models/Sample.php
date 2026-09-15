<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nikoleesg\Survey\Enums\ParadataLabelEnum;
use Nikoleesg\Survey\Traits\BelongsToSurvey;

/**
 * One interview of a survey. Consumer apps extend this model and add their
 * own columns to the samples table; point config('survey.sample_model') at
 * the subclass.
 *
 * Columns the package writes (do not remove or rename): id, survey_id,
 * interview_number, sub_questionnaire_number, interviewer_id,
 * interview_time_in_seconds, number_of_screens_shown, last_contact_at.
 */
class Sample extends Model
{
    use BelongsToSurvey;

    protected $table = 'survey_samples';

    protected $guarded = [];

    protected $casts = [
        'interview_number'          => 'integer',
        'sub_questionnaire_number'  => 'integer',
        'interview_time_in_seconds' => 'integer',
        'number_of_screens_shown'   => 'integer',
        'last_contact_at'           => 'datetime',
    ];

    public const UPSERT_KEYS = ['survey_id', 'interview_number'];

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

    public function openAnswers(): HasMany
    {
        return $this->hasMany(config('survey.open_answer_model'), 'sample_id');
    }

    public function paradata(): HasMany
    {
        return $this->hasMany(config('survey.paradata_model'), 'sample_id');
    }

    /**
     * The single paradata row for one label, e.g.
     * $sample->paradataOf(ParadataLabelEnum::DEVICE_ID)->first()?->result
     */
    public function paradataOf(ParadataLabelEnum|string $label): HasOne
    {
        return $this->paradata()->one()->ofLabel($label);
    }
}
