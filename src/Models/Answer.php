<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LaraUtil\Foundation\Traits\HasUuid;
use Spatie\LaravelData\WithData;
use Nikoleesg\Survey\Traits\HasTablePrefix;
use Nikoleesg\Survey\Data\AnswerData;

class Answer extends Model
{
    use HasUuid, HasTablePrefix;
    use WithData;

    protected $guarded = [];

    protected $dataClass = AnswerData::class;

    protected $casts = [
        'result' => 'array',
    ];

    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::class);
    }

    public function sample(): MorphTo
    {
        return $this->morphTo();
    }
}
