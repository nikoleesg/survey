<?php

use Nikoleesg\Survey\Enums\ParadataLabelEnum;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Models\Answer;
use Nikoleesg\Survey\Models\OpenAnswer;
use Nikoleesg\Survey\Models\Paradata;
use Nikoleesg\Survey\Models\Sample;
use Nikoleesg\Survey\Models\Variable;

it('fills survey_id from config when not given', function () {
    config()->set('survey.survey_id', 'configured');

    $sample = Sample::create(['interview_number' => 1]);
    $variable = Variable::create(['name' => 'Q1', 'type' => VariableTypeEnum::SINGLE]);

    expect($sample->survey_id)->toBe('configured')
        ->and($variable->survey_id)->toBe('configured')
        ->and(Sample::ofSurvey()->count())->toBe(1)
        ->and(Variable::ofSurvey()->count())->toBe(1)
        ->and(Variable::ofSurvey('other')->count())->toBe(0);
});

it('keeps an explicit survey_id', function () {
    $sample = Sample::create(['survey_id' => 'wave-2', 'interview_number' => 1]);

    expect($sample->survey_id)->toBe('wave-2')
        ->and(Sample::ofSurvey('wave-2')->count())->toBe(1)
        ->and(Sample::ofSurvey()->count())->toBe(0);
});

it('scopes variable slugs per survey and keeps them stable on rename', function () {
    $a = Variable::create(['survey_id' => 'a', 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE]);
    $b = Variable::create(['survey_id' => 'b', 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE]);
    $a2 = Variable::create(['survey_id' => 'a', 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE]);

    expect($a->slug)->toBe('q1')
        ->and($b->slug)->toBe('q1')
        ->and($a2->slug)->toBe('q1_1');

    $a->update(['name' => 'Renamed']);

    expect($a->fresh()->slug)->toBe('q1');
});

it('casts variable columns', function () {
    $variable = Variable::create([
        'name'       => 'Q1',
        'type'       => VariableTypeEnum::MULTIPLE,
        'options'    => ['rows' => 3],
        'is_dynamic' => 1,
        'is_active'  => 0,
    ])->fresh();

    expect($variable->type)->toBe(VariableTypeEnum::MULTIPLE)
        ->and($variable->options)->toBe(['rows' => 3])
        ->and($variable->is_dynamic)->toBeTrue()
        ->and($variable->is_active)->toBeFalse()
        ->and(Variable::active()->count())->toBe(0)
        ->and(Variable::inactive()->count())->toBe(1)
        ->and($variable->getData()->options)->toBe(['rows' => 3]);
});

it('relates sample to answers, open answers and paradata', function () {
    $sample = Sample::create(['interview_number' => 7]);
    $other = Sample::create(['interview_number' => 8]);
    $variable = Variable::create(['name' => 'Q1', 'type' => VariableTypeEnum::SINGLE]);

    Answer::create(['sample_id' => $sample->id, 'variable_id' => $variable->id, 'result' => ['q1' => 3]]);
    Answer::create(['sample_id' => $other->id, 'variable_id' => $variable->id, 'result' => ['q1' => 4]]);
    OpenAnswer::create(['sample_id' => $sample->id, 'position' => 10, 'length' => 5, 'verbatim_text' => 'hi']);
    Paradata::create(['sample_id' => $sample->id, 'label' => 'DeviceId', 'result' => 'abc']);

    expect($sample->answers)->toHaveCount(1)
        ->and($sample->getAnswers())->toBe(['q1' => 3])
        ->and($sample->openAnswers)->toHaveCount(1)
        ->and($sample->paradata)->toHaveCount(1)
        ->and($sample->paradataOf(ParadataLabelEnum::DEVICE_ID)->first()->result)->toBe('abc')
        ->and($sample->paradataOf('DeviceId')->first()->result)->toBe('abc')
        ->and($sample->paradataOf(ParadataLabelEnum::QUOTA)->first())->toBeNull()
        ->and($sample->answers->first()->sample->is($sample))->toBeTrue()
        ->and($sample->answers->first()->variable->is($variable))->toBeTrue()
        ->and($variable->answers)->toHaveCount(2)
        ->and(Answer::ofSample($sample->id)->count())->toBe(1)
        ->and(Answer::ofSurvey()->count())->toBe(2)
        ->and(Answer::ofSurvey('none')->count())->toBe(0);
});

it('lists enum labels from cases', function () {
    expect(VariableTypeEnum::labels())->toBe([
        1 => 'Single', 2 => 'Multiple', 3 => 'Numerical', 4 => 'Open', 5 => 'Text',
        6 => 'Calculation', 7 => 'Matrix', 9 => 'Dummy', 11 => 'Datetime', 12 => 'Date', 13 => 'Time',
    ]);
});
