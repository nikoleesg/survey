<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\WithData;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Nikoleesg\Survey\Traits\BelongsToSurvey;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Nikoleesg\Survey\Data\VariableData;
use Nikoleesg\Survey\Enums\VariableTypeEnum;

class Variable extends Model implements Sortable
{
    use HasUuids, BelongsToSurvey;
    use WithData, SortableTrait, HasSlug;

    protected $table = 'survey_variables';

    protected $dataClass = VariableData::class;

    /**
     * Generate the uuid on a secondary column; `id` stays auto-incrementing.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $guarded = [];

    protected $casts = [
        'type'       => VariableTypeEnum::class,
        'codes'      => 'array',
        'options'    => 'array',
        'formula'    => 'array',
        'is_dynamic' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public $sortable = [
        'order_column_name'  => 'order_column',
        'sort_when_creating' => true,
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(config('survey.closed_answer_model'), 'variable_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    /**
     * The slug is the key of this variable's answer inside answers.result,
     * so it is unique per survey and never regenerated once set.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->usingSeparator('_')
            ->doNotGenerateSlugsOnUpdate()
            ->extraScope(fn (Builder $query) => $query->where('survey_id', $this->survey_id));
    }
}
