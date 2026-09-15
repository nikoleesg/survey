<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Enums\ParadataLabelEnum;
use Nikoleesg\Survey\Traits\BelongsToSample;

/**
 * @property int $id
 * @property int $sample_id
 * @property string $label
 * @property string|null $result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Paradata extends Model
{
    use BelongsToSample;

    protected $table = 'survey_paradatas';

    protected $guarded = [];

    public const UPSERT_KEYS = ['sample_id', 'label'];

    /**
     * The class bound to config('survey.paradata_model'): this model, or the
     * consumer app's subclass of it.
     *
     * @return class-string<Paradata>
     */
    public static function modelClass(): string
    {
        return config('survey.paradata_model');
    }

    public function scopeOfLabel(Builder $query, ParadataLabelEnum|string $label): void
    {
        $query->where('label', $label instanceof ParadataLabelEnum ? $label->value : $label);
    }
}
