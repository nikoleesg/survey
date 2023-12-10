<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LaraUtil\Foundation\Traits\HasUuid;
use Spatie\LaravelData\WithData;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Nikoleesg\Survey\Traits\HasTablePrefix;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Nikoleesg\Survey\Data\VariableData;
use Nikoleesg\Survey\Enums\VariableTypeEnum;

class Variable extends Model implements Sortable
{
    use HasUuid, HasTablePrefix;
    use WithData, SortableTrait, HasSlug;

    protected $dataClass = VariableData::class;

    protected $guarded = [];

    protected $casts = [
        'type'    => VariableTypeEnum::class,
        'codes'   => 'array',
        'formula' => 'array'
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    public function scopeOfSurvey(Builder $query, string $surveyId): void
    {
        $query->where('survey_id', '=', $surveyId);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', '=', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('status', '=', false);
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->usingSeparator('_');
    }
}
