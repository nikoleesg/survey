<?php

use Illuminate\Support\Collection;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Models\Answer;
use Nikoleesg\Survey\Models\Sample;
use Nikoleesg\Survey\Models\Variable;
use Nikoleesg\Survey\Services\AnswerService;

/**
 * One SINGLE variable "Q1" and one answered interview per survey; both
 * surveys share interview number 1 so a missing survey scope would mix them.
 */
function seedSurvey(string $surveyId, int $answer): array
{
    $variable = Variable::create(['survey_id' => $surveyId, 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    $sample = Sample::create(['survey_id' => $surveyId, 'interview_number' => 1]);
    Answer::create(['sample_id' => $sample->id, 'variable_id' => $variable->id, 'result' => ['q1' => $answer]]);

    return [$variable, $sample];
}

it('returns only answers of the resolved survey when interview numbers collide', function () {
    [$variableA] = seedSurvey('survey-a', 1);
    [$variableB] = seedSurvey('survey-b', 2);

    $answers = (new AnswerService('survey-a'))->getAnswers();

    expect($answers)->toBeInstanceOf(Collection::class)
        ->and($answers)->toHaveCount(1)
        ->and($answers[1])->toHaveCount(1)
        ->and($answers[1][0]['variable_id'])->toBe($variableA->id)
        ->and($answers[1][0]['answer'])->toBe(1);

    expect((new AnswerService('survey-b'))->getAnswers()[1][0])
        ->toMatchArray(['variable_id' => $variableB->id, 'answer' => 2]);
});

it('ignores a variable id belonging to another survey', function () {
    seedSurvey('survey-a', 1);
    [$variableB] = seedSurvey('survey-b', 2);

    expect((new AnswerService('survey-a'))->getAnswers(null, [$variableB->id]))->toBeEmpty();
});

it('returns an empty collection when no variables match', function () {
    seedSurvey('survey-a', 1);

    $service = new AnswerService('survey-a');

    expect($service->getAnswers([1], [999999]))->toBeInstanceOf(Collection::class)->toBeEmpty()
        ->and($service->getAnswer(1, 999999))->toBeNull();
});

it('returns null rather than an empty string for a missing open answer', function () {
    $variable = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 50, 'length' => 5, 'fraction' => 0]);
    $sample = Sample::create(['survey_id' => 'survey-a', 'interview_number' => 1]);
    Answer::create(['sample_id' => $sample->id, 'variable_id' => $variable->id, 'result' => ['q4' => null]]);

    $service = new AnswerService('survey-a');

    expect($service->getAnswer(1, $variable->id)['answer'])->toBeNull();

    Answer::query()->update(['result' => ['q4' => "  spaced \n out  "]]);

    expect($service->getAnswer(1, $variable->id)['answer'])->toBe('spaced out');

    // several coded verbatims are squished one by one, keys kept
    Answer::query()->update(['result' => ['q4' => [97 => "  one \n", 98 => ' two  ']]]);

    expect($service->getAnswer(1, $variable->id)['answer'])->toBe([97 => 'one', 98 => 'two']);
});

it('returns null rather than now for an unanswered datetime', function () {
    $variable = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q5', 'type' => VariableTypeEnum::DATETIME, 'position' => 41, 'length' => 18, 'fraction' => 0]);
    $sample = Sample::create(['survey_id' => 'survey-a', 'interview_number' => 1]);
    Answer::create(['sample_id' => $sample->id, 'variable_id' => $variable->id, 'result' => ['q5' => null]]);

    expect((new AnswerService('survey-a'))->getAnswer(1, $variable->id)['answer'])->toBeNull();
});

it('rejects an interview filter that is not an integer list', function (mixed $interviewId) {
    (new AnswerService('survey-a'))->setInterview($interviewId);
})->with([
    'string list' => [['1']],
    'keyed array' => [['a' => 1]],
])->throws(InvalidArgumentException::class);

it('filters by interview number', function () {
    $variable = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);

    foreach ([1 => 5, 2 => 6, 3 => 7] as $interview => $value) {
        $sample = Sample::create(['survey_id' => 'survey-a', 'interview_number' => $interview]);
        Answer::create(['sample_id' => $sample->id, 'variable_id' => $variable->id, 'result' => ['q1' => $value]]);
    }

    $answers = (new AnswerService('survey-a'))->getAnswers([1, 3]);

    expect($answers->keys()->all())->toBe([1, 3])
        ->and($answers[3][0]['answer'])->toBe(7);
});
