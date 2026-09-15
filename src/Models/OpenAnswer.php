<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Traits\BelongsToSample;
use Nikoleesg\Survey\Traits\HasTablePrefix;

class OpenAnswer extends Model
{
    use HasTablePrefix, BelongsToSample;

    protected $guarded = [];

    public const UPSERT_KEYS = ['sample_id', 'position', 'length', 'code_number'];
}
