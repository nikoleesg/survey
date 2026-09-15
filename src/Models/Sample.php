<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nikoleesg\Survey\Enums\ChannelEnum;
use Nikoleesg\Survey\Enums\InterruptIndicationEnum;
use Nikoleesg\Survey\Enums\ParadataLabelEnum;
use Nikoleesg\Survey\Traits\BelongsToSurvey;

/**
 * One interview of a survey. Consumer apps extend this model and add their
 * own columns to the samples table; point config('survey.sample_model') at
 * the subclass.
 *
 * Columns the package writes (do not remove or rename): id, survey_id,
 * interview_number, and the closed answer file header: sub_questionnaire_number,
 * interview_time_in_seconds, number_of_screens_shown, interrupt_indication,
 * interviewer_id, last_contact_at, odin_version, idle_time_in_seconds,
 * week_number, week_version_number, family_member_number, channel.
 *
 * @property int $id
 * @property string $survey_id
 * @property int $interview_number
 * @property int|null $sub_questionnaire_number
 * @property int|null $interview_time_in_seconds
 * @property int|null $number_of_screens_shown
 * @property InterruptIndicationEnum|null $interrupt_indication
 * @property string|null $interviewer_id
 * @property \Illuminate\Support\Carbon|null $last_contact_at
 * @property string|null $odin_version
 * @property int|null $idle_time_in_seconds
 * @property int|null $week_number
 * @property int|null $week_version_number
 * @property int|null $family_member_number
 * @property ChannelEnum|null $channel
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
        'interrupt_indication'      => InterruptIndicationEnum::class,
        'last_contact_at'           => 'datetime',
        'idle_time_in_seconds'      => 'integer',
        'week_number'               => 'integer',
        'week_version_number'       => 'integer',
        'family_member_number'      => 'integer',
        'channel'                   => ChannelEnum::class,
    ];

    public const UPSERT_KEYS = ['survey_id', 'interview_number'];

    /**
     * The class bound to config('survey.sample_model'): this model, or the
     * consumer app's subclass of it.
     *
     * @return class-string<Sample>
     */
    public static function modelClass(): string
    {
        return config('survey.sample_model');
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::modelClass(), 'sample_id');
    }

    /**
     * All the sample's answers merged into one array keyed by variable slug.
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

    /**
     * @return HasMany<Paradata, $this>
     */
    public function paradata(): HasMany
    {
        return $this->hasMany(Paradata::modelClass(), 'sample_id');
    }

    /**
     * The single paradata row for one label, e.g.
     * $sample->paradataOf(ParadataLabelEnum::DEVICE_ID)->first()?->result
     *
     * @return HasOne<Paradata, $this>
     */
    public function paradataOf(ParadataLabelEnum|string $label): HasOne
    {
        return $this->paradata()->one()->ofLabel($label);
    }
}
