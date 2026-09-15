<?php

use Nikoleesg\Survey\Data\AnswerData;
use Nikoleesg\Survey\Data\OpenAnswerData;
use Nikoleesg\Survey\Data\ParadataData;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Exceptions\MissingSurveyIdException;
use Nikoleesg\Survey\Exceptions\NoDataLoadedException;
use Nikoleesg\Survey\Exceptions\SurveyException;
use Nikoleesg\Survey\Exceptions\UnparseableColumnException;
use Nikoleesg\Survey\Exceptions\UnreadableFileException;
use Nikoleesg\Survey\Services\AnswerService;
use Nikoleesg\Survey\Models\Answer;
use Nikoleesg\Survey\Models\OpenAnswer;
use Nikoleesg\Survey\Models\Paradata;
use Nikoleesg\Survey\Models\Sample;
use Nikoleesg\Survey\Models\Variable;
use Nikoleesg\Survey\Services\DataService;
use Spatie\LaravelData\DataCollection;

function writeFixture(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'survey_');
    file_put_contents($path, $content);

    return $path;
}

it('loads open answers from file into a DataCollection', function () {
    $file = writeFixture(
        "000000010100010005 Hello world\n".
        "0000000201000200501 Second answer\n"
    );

    $data = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->getData();

    expect($data)->toBeInstanceOf(DataCollection::class)
        ->and($data->getDataClass())->toBe(OpenAnswerData::class)
        ->and($data)->toHaveCount(2);

    $first = $data->toArray()[0];
    expect($first)->toMatchArray([
        'interview_number'         => 1,
        'sub_questionnaire_number' => 1,
        'position'                 => 10,
        'length'                   => 5,
        'code_number'              => 0,
        'verbatim_text'            => 'Hello world',
        'survey_id'                => 'survey-a',
    ]);

    expect($data->toArray()[1])->toMatchArray([
        'interview_number' => 2,
        'position'         => 20,
        'code_number'      => 1,
        'verbatim_text'    => 'Second answer',
    ]);

    unlink($file);
});

it('loads paradata from a UTF-16LE file into a DataCollection', function () {
    $content = "\xFF\xFE".mb_convert_encoding(
        "00000001\tStartTime\t2024-01-15 10:30:00\r\n00000001\tDevice\tPhone\r\n",
        'UTF-16LE',
        'UTF-8'
    );
    $file = writeFixture($content);

    $data = (new DataService())->getParadatafromFile($file, 'survey-a')->getData();

    expect($data)->toBeInstanceOf(DataCollection::class)
        ->and($data->getDataClass())->toBe(ParadataData::class)
        ->and($data)->toHaveCount(2)
        ->and($data->toArray()[0])->toMatchArray([
            'interview_number' => 1,
            'label'            => 'StartTime',
            'result'           => '2024-01-15 10:30:00',
            'survey_id'        => 'survey-a',
        ])
        ->and($data->toArray()[1])->toMatchArray([
            'label'  => 'Device',
            'result' => 'Phone',
        ]);

    unlink($file);
});

it('loads closed answers from file into a DataCollection', function () {
    $surveyId = 'survey-a';

    $single = Variable::create(['survey_id' => $surveyId, 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    $multi = Variable::create(['survey_id' => $surveyId, 'name' => 'Q2', 'type' => VariableTypeEnum::MULTIPLE, 'position' => 42, 'length' => 3, 'fraction' => 0]);
    $numeric = Variable::create(['survey_id' => $surveyId, 'name' => 'Q3', 'type' => VariableTypeEnum::NUMERICAL, 'position' => 45, 'length' => 3, 'fraction' => 2]);
    $open = Variable::create(['survey_id' => $surveyId, 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 50, 'length' => 5, 'fraction' => 0]);

    $sample = Sample::create(['survey_id' => $surveyId, 'interview_number' => 1]);

    OpenAnswer::create([
        'sample_id'     => $sample->id,
        'position'      => 50,
        'length'        => 5,
        'verbatim_text' => 'Free text',
    ]);

    // cols 1-8 interview, 9-10 sub-q, 11-15 seconds, 16-19 screens, 21-28 interviewer, 29-40 datetime, then variables
    $row = '00000001' . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030' . '3' . '101' . '12345' . '     ';
    $file = writeFixture($row."\n");

    $data = (new DataService())->setSurvey($surveyId)->getClosedAnswersFromFile($file)->getData();

    expect($data)->toBeInstanceOf(DataCollection::class)
        ->and($data->getDataClass())->toBe(AnswerData::class)
        ->and($data)->toHaveCount(4);

    $byVariable = collect($data->toArray())->keyBy('variable_id');

    expect($byVariable[$single->id])->toMatchArray([
        'survey_id'        => $surveyId,
        'interview_number' => 1,
        'result'           => json_encode(['q1' => 3]),
    ]);
    expect($byVariable[$multi->id]['result'])->toBe(json_encode(['q2' => [1, 0, 1]]));
    expect($byVariable[$numeric->id]['result'])->toBe(json_encode(['q3' => 123.45]));
    expect($byVariable[$open->id]['result'])->toBe(json_encode(['q4' => 'Free text']));

    unlink($file);
});

it('prefers an explicit survey id over setSurvey() when loading closed answers', function () {
    // Regression for #15: the argument used to be clobbered by $this->surveyId.
    $setA = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    $argB = Variable::create(['survey_id' => 'survey-b', 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);

    $row = '00000001' . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030' . '7';
    $file = writeFixture($row."\n");

    $data = (new DataService())->setSurvey('survey-a')->getClosedAnswersFromFile($file, 'survey-b')->getData();

    expect($data)->toHaveCount(1)
        ->and($data[0]->survey_id)->toBe('survey-b')
        ->and($data[0]->variable_id)->toBe($argB->id)
        ->and($data[0]->variable_id)->not->toBe($setA->id);

    unlink($file);
});

// 40-column system header; variable columns start at position 41
function closedAnswerRow(string $variables, string $interview = '00000001'): string
{
    return $interview . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030' . $variables . "\n";
}

function loadSingleVariable(VariableTypeEnum $type, int $length, string $columns, int $fraction = 0): mixed
{
    $variable = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q1', 'type' => $type, 'position' => 41, 'length' => $length, 'fraction' => $fraction]);

    $file = writeFixture(closedAnswerRow($columns));

    $data = (new DataService())->getClosedAnswersFromFile($file, 'survey-a')->getData()->toArray();

    unlink($file);

    return json_decode($data[0]['result'], true)[$variable->slug];
}

it('maps each variable type from its columns', function (VariableTypeEnum $type, int $length, string $columns, mixed $expected, int $fraction = 0) {
    expect(loadSingleVariable($type, $length, $columns, $fraction))->toBe($expected);
})->with([
    'single'                => [VariableTypeEnum::SINGLE, 2, ' 7', 7],
    'single code 0'         => [VariableTypeEnum::SINGLE, 1, '0', 0],
    'multiple'              => [VariableTypeEnum::MULTIPLE, 3, '1 1', [1, 0, 1]],
    'numerical'             => [VariableTypeEnum::NUMERICAL, 3, ' 42', 42],
    'numerical fraction'    => [VariableTypeEnum::NUMERICAL, 3, '12345', 123.45, 2],
    'alpha trims padding'   => [VariableTypeEnum::ALPHA, 6, 'Blue  ', 'Blue'],
    'alpha keeps inner'     => [VariableTypeEnum::ALPHA, 9, 'Dark blue', 'Dark blue'],
    'datetime'              => [VariableTypeEnum::DATETIME, 18, '2024/01/15 1030:45', '2024-01-15 10:30:45'],
    'date'                  => [VariableTypeEnum::DATE, 10, '2024/01/15', '2024-01-15'],
    'time'                  => [VariableTypeEnum::TIME, 4, '1030', '10:30:00'],
    'calculable'            => [VariableTypeEnum::CALCULABLE, 3, '123', null],
    'matrix'                => [VariableTypeEnum::MATRIX, 3, '123', null],
    'dummy'                 => [VariableTypeEnum::DUMMY, 3, '123', null],
]);

it('stores a blank column as null, not 0', function (VariableTypeEnum $type, int $length) {
    expect(loadSingleVariable($type, $length, str_repeat(' ', $length)))->toBeNull();
})->with([
    'single'    => [VariableTypeEnum::SINGLE, 2],
    'multiple'  => [VariableTypeEnum::MULTIPLE, 3],
    'numerical' => [VariableTypeEnum::NUMERICAL, 5],
    'alpha'     => [VariableTypeEnum::ALPHA, 6],
    'datetime'  => [VariableTypeEnum::DATETIME, 18],
    'date'      => [VariableTypeEnum::DATE, 10],
    'time'      => [VariableTypeEnum::TIME, 4],
]);

it('stores a column that runs past the end of the row as null', function () {
    expect(loadSingleVariable(VariableTypeEnum::SINGLE, 2, ''))->toBeNull();
});

it('throws a descriptive exception for a malformed date column', function () {
    expect(fn () => loadSingleVariable(VariableTypeEnum::DATE, 10, '15-01-2024'))
        ->toThrow(UnparseableColumnException::class, 'Interview 1, variable "Q1": column value "15-01-2024" does not match the Date format "Y/m/d".');
});

it('reads date formats from config', function () {
    config()->set('survey.formats.date', 'd-m-Y');

    expect(loadSingleVariable(VariableTypeEnum::DATE, 10, '15-01-2024'))->toBe('2024-01-15');
});

it('throws when persisting before any file is loaded', function () {
    (new DataService())->persist();
})->throws(NoDataLoadedException::class);

it('throws when no survey id can be resolved', function (string $method) {
    config()->set('survey.survey_id', null);

    (new DataService())->{$method}(writeFixture(''));
})->with(['getOpenAnswersFromFile', 'getParadatafromFile', 'getClosedAnswersFromFile'])
    ->throws(MissingSurveyIdException::class);

it('throws from AnswerService when no survey id can be resolved', function () {
    config()->set('survey.survey_id', '');

    (new AnswerService())->getAnswers();
})->throws(MissingSurveyIdException::class);

it('throws a named exception for an unreadable file', function (string $method) {
    (new DataService())->{$method}('/nonexistent/survey.dat', 'survey-a');
})->with(['getOpenAnswersFromFile', 'getParadatafromFile', 'getClosedAnswersFromFile'])
    ->throws(UnreadableFileException::class, '/nonexistent/survey.dat');

it('names the loader in the unreadable file message', function () {
    expect(fn () => (new DataService())->getOpenAnswersFromFile('/nonexistent/survey.dat', 'survey-a'))
        ->toThrow(UnreadableFileException::class, 'getOpenAnswersFromFile');
});

it('exposes every package exception under a common base', function () {
    expect(NoDataLoadedException::make())->toBeInstanceOf(SurveyException::class)
        ->and(MissingSurveyIdException::make())->toBeInstanceOf(SurveyException::class)
        ->and(UnreadableFileException::make('f', 'l'))->toBeInstanceOf(SurveyException::class)
        ->and(UnparseableColumnException::make(1, new Variable(['name' => 'Q1', 'type' => VariableTypeEnum::DATE]), 'Y/m/d', 'x'))->toBeInstanceOf(SurveyException::class);
});

it('persists open answers, creating stub samples and upserting on re-run', function () {
    $file = writeFixture(
        "000000010100010005 Hello world\n".
        "0000000201000200501 Second answer\n"
    );

    $service = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->persist();

    expect(Sample::ofSurvey('survey-a')->count())->toBe(2)
        ->and(OpenAnswer::count())->toBe(2);

    $sample = Sample::ofSurvey('survey-a')->where('interview_number', 1)->first();
    $row = OpenAnswer::where('sample_id', $sample->id)->first();

    expect($row)->toMatchArray([
        'position'      => 10,
        'length'        => 5,
        'code_number'   => 0,
        'verbatim_text' => 'Hello world',
    ]);

    // re-persist with changed text: same rows, updated verbatim
    file_put_contents($file, "000000010100010005 Hello again\n0000000201000200501 Second answer\n");
    $service->getOpenAnswersFromFile($file, 'survey-a')->persist();

    expect(Sample::count())->toBe(2)
        ->and(OpenAnswer::count())->toBe(2)
        ->and($row->fresh()->verbatim_text)->toBe('Hello again');

    unlink($file);
});

it('persists paradata against an existing sample', function () {
    $sample = Sample::create(['survey_id' => 'survey-a', 'interview_number' => 1]);

    $content = "\xFF\xFE".mb_convert_encoding(
        "00000001\tStartTime\t2024-01-15 10:30:00\r\n00000001\tDevice\tPhone\r\n",
        'UTF-16LE',
        'UTF-8'
    );
    $file = writeFixture($content);

    (new DataService())->getParadatafromFile($file, 'survey-a')->persist();

    expect(Sample::count())->toBe(1)
        ->and(Paradata::count())->toBe(2)
        ->and($sample->paradataOf('Device')->first()->result)->toBe('Phone');

    unlink($file);
});

it('persists closed answers in chunks', function () {
    config()->set('survey.persist_chunk_size', 2);

    $surveyId = 'survey-a';
    $q1 = Variable::create(['survey_id' => $surveyId, 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    $q2 = Variable::create(['survey_id' => $surveyId, 'name' => 'Q2', 'type' => VariableTypeEnum::SINGLE, 'position' => 42, 'length' => 1, 'fraction' => 0]);
    $q3 = Variable::create(['survey_id' => $surveyId, 'name' => 'Q3', 'type' => VariableTypeEnum::SINGLE, 'position' => 43, 'length' => 1, 'fraction' => 0]);

    $header = fn (string $interview) => $interview . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030';
    $file = writeFixture($header('00000001')."123\n".$header('00000002')."456\n");

    (new DataService())->setSurvey($surveyId)->getClosedAnswersFromFile($file)->persist();

    expect(Sample::ofSurvey($surveyId)->count())->toBe(2)
        ->and(Answer::count())->toBe(6);

    $second = Sample::ofSurvey($surveyId)->where('interview_number', 2)->first();

    expect($second->getAnswers())->toBe(['q1' => 4, 'q2' => 5, 'q3' => 6]);

    unlink($file);
});
