<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\LaravelData\WithData;
use Nikoleesg\Survey\Traits\BelongsToSample;
use Nikoleesg\Survey\Data\AnswerData;

class Answer extends Model
{
    use BelongsToSample;
    use WithData;

    protected $table = 'survey_answers';

    protected $guarded = [];

    protected $dataClass = AnswerData::class;

    protected $casts = [
        'result' => 'array',
    ];

    public const UPSERT_KEYS = ['sample_id', 'variable_id'];

    public function variable(): BelongsTo
    {
        return $this->belongsTo(config('survey.variable_model'), 'variable_id');
    }
}
