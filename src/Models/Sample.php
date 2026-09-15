<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Traits\BelongsToSurvey;
use Nikoleesg\Survey\Traits\HasAnswers;
use Nikoleesg\Survey\Traits\HasOpenAnswers;
use Nikoleesg\Survey\Traits\HasParadata;
use Nikoleesg\Survey\Traits\HasTablePrefix;

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
    use HasTablePrefix, BelongsToSurvey;
    use HasAnswers, HasOpenAnswers, HasParadata;

    protected $guarded = [];

    protected $casts = [
        'interview_number'          => 'integer',
        'sub_questionnaire_number'  => 'integer',
        'interview_time_in_seconds' => 'integer',
        'number_of_screens_shown'   => 'integer',
        'last_contact_at'           => 'datetime',
    ];

    public const UPSERT_KEYS = ['survey_id', 'interview_number'];
}
