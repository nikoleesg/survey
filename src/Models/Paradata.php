<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Enums\ParadataLabelEnum;
use Nikoleesg\Survey\Traits\BelongsToSample;

class Paradata extends Model
{
    use BelongsToSample;

    protected $table = 'survey_paradatas';

    protected $guarded = [];

    public const UPSERT_KEYS = ['sample_id', 'label'];

    public function scopeOfLabel(Builder $query, ParadataLabelEnum|string $label): void
    {
        $query->where('label', $label instanceof ParadataLabelEnum ? $label->value : $label);
    }
}
