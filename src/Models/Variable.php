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

/**
 * @property int $id
 * @property string $uuid
 * @property string $survey_id
 * @property string $name
 * @property string $slug
 * @property string|null $label
 * @property VariableTypeEnum $type
 * @property array|null $codes
 * @property array|null $options
 * @property int|null $position
 * @property int|null $length
 * @property int|null $fraction
 * @property array|null $formula
 * @property string|null $remark
 * @property bool $is_dynamic
 * @property bool $is_active
 * @property int|null $order_column
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /**
     * The class bound to config('survey.variable_model'): this model, or the
     * consumer app's subclass of it.
     *
     * @return class-string<Variable>
     */
    public static function modelClass(): string
    {
        return config('survey.variable_model');
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::modelClass(), 'variable_id');
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
