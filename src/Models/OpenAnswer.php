<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Traits\BelongsToSample;

class OpenAnswer extends Model
{
    use BelongsToSample;

    protected $table = 'survey_open_answers';

    protected $guarded = [];

    public const UPSERT_KEYS = ['sample_id', 'position', 'length', 'code_number'];
}
