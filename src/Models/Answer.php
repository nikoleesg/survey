<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\LaravelData\WithData;
use Nikoleesg\Survey\Traits\BelongsToSample;
use Nikoleesg\Survey\Data\AnswerData;

/**
 * @property int $id
 * @property int $sample_id
 * @property int $variable_id
 * @property array $result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /**
     * The class bound to config('survey.closed_answer_model'): this model, or the
     * consumer app's subclass of it.
     *
     * @return class-string<Answer>
     */
    public static function modelClass(): string
    {
        return config('survey.closed_answer_model');
    }

    /**
     * @return BelongsTo<Variable, $this>
     */
    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::modelClass(), 'variable_id');
    }
}
