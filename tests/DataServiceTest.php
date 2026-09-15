<?php

use Nikoleesg\Survey\Data\AnswerData;
use Nikoleesg\Survey\Data\ParadataData;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Exceptions\MissingSurveyIdException;
use Nikoleesg\Survey\Exceptions\NoDataLoadedException;
use Nikoleesg\Survey\Exceptions\SurveyException;
use Nikoleesg\Survey\Exceptions\UnparseableColumnException;
use Nikoleesg\Survey\Exceptions\UnreadableFileException;
use Nikoleesg\Survey\Services\AnswerService;
use Nikoleesg\Survey\Models\Answer;
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

it('loads open answers from file as answers of the matching OPEN variables', function () {
    $q4 = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 10, 'length' => 5]);
    $q5Other = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q5_97_Other', 'type' => VariableTypeEnum::OPEN, 'position' => 20, 'length' => 5]);
    // same columns, other survey: must not match
    Variable::create(['survey_id' => 'survey-b', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 10, 'length' => 5]);

    $file = writeFixture(
        "000000010100010005 Hello world\n".
        "00000002010002000597 Second answer\n".
        // interview 2 skipped Q4 (optional); no row for it
        "000000030100099005 no variable at these columns\n"
    );

    $data = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->getData();

    expect($data)->toBeInstanceOf(DataCollection::class)
        ->and($data->getDataClass())->toBe(AnswerData::class)
        ->and($data)->toHaveCount(2);

    $rows = $data->toArray();

    expect($rows[0])->toMatchArray([
        'survey_id'        => 'survey-a',
        'variable_id'      => $q4->id,
        'interview_number' => 1,
        'result'           => json_encode(['q4' => 'Hello world']),
    ]);

    // the code number is implied by Q5's closed answer; only the text is kept
    expect($rows[1])->toMatchArray([
        'variable_id'      => $q5Other->id,
        'interview_number' => 2,
        'result'           => json_encode(['q5_97_other' => 'Second answer']),
    ]);

    unlink($file);
});

it('keys several coded open answers of one variable by code number', function () {
    Variable::create(['survey_id' => 'survey-a', 'name' => 'Q5_Other', 'type' => VariableTypeEnum::OPEN, 'position' => 20, 'length' => 5]);

    $file = writeFixture(
        "00000001010002000598 Other two\n".
        "00000001010002000597 Other one\n"
    );

    $rows = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->getData()->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['result'])->toBe(json_encode(['q5_other' => [97 => 'Other one', 98 => 'Other two']]));

    unlink($file);
});

it('keeps verbatim text intact: multibyte characters, inner spacing, CRLF', function () {
    Variable::create(['survey_id' => 'survey-a', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 10, 'length' => 5]);

    $file = writeFixture(
        "000000010100010005 Café,  très  bien 你好 👍\r\n".
        "000000020100010005 \tindented\t\r\n"
    );

    $rows = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->getData()->toArray();

    expect($rows)->toHaveCount(2)
        ->and(json_decode($rows[0]['result'], true))->toBe(['q4' => 'Café,  très  bien 你好 👍'])
        ->and(json_decode($rows[1]['result'], true))->toBe(['q4' => 'indented']);

    unlink($file);
});

it('parses the open answer header by fixed columns', function (string $row, array $expected) {
    expect((new DataService())->parseOpenAnswerString($row))->toBe($expected);
})->with([
    'pure open'          => ["000000010100010005 text\n", ['interview_number' => 1, 'sub_questionnaire_number' => 1, 'position' => 10, 'length' => 5, 'code_number' => 0, 'verbatim_text' => 'text']],
    'coded'              => ["00000002010002000597 text\n", ['interview_number' => 2, 'sub_questionnaire_number' => 1, 'position' => 20, 'length' => 5, 'code_number' => 97, 'verbatim_text' => 'text']],
    'space-padded code'  => ["000000010100010005   text\n", ['interview_number' => 1, 'sub_questionnaire_number' => 1, 'position' => 10, 'length' => 5, 'code_number' => 0, 'verbatim_text' => 'text']],
    'empty verbatim'     => ["000000010100010005\n", ['interview_number' => 1, 'sub_questionnaire_number' => 1, 'position' => 10, 'length' => 5, 'code_number' => 0, 'verbatim_text' => '']],
    'coded, empty'       => ["00000001010001000597\r\n", ['interview_number' => 1, 'sub_questionnaire_number' => 1, 'position' => 10, 'length' => 5, 'code_number' => 97, 'verbatim_text' => '']],
    'blank after header' => ["000000010100010005    \n", ['interview_number' => 1, 'sub_questionnaire_number' => 1, 'position' => 10, 'length' => 5, 'code_number' => 0, 'verbatim_text' => '']],
]);

it('does not turn an empty verbatim into an answer', function () {
    Variable::create(['survey_id' => 'survey-a', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 10, 'length' => 5]);

    $file = writeFixture("000000010100010005\n000000020100010005   \n000000030100010005 real\n");

    $rows = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->getData()->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['interview_number'])->toBe(3);

    unlink($file);
});

it('ignores inactive OPEN variables when loading open answers', function () {
    Variable::create(['survey_id' => 'survey-a', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 10, 'length' => 5, 'is_active' => false]);

    $file = writeFixture("000000010100010005 Hello world\n");

    expect((new DataService())->getOpenAnswersFromFile($file, 'survey-a')->getData())->toHaveCount(0);

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

it('keeps the first paradata record when the file has no BOM', function () {
    $content = mb_convert_encoding(
        "00000001\tStartTime\t2024-01-15 10:30:00\r\n00000001\tDevice\tPhone\r\n",
        'UTF-16LE',
        'UTF-8'
    );
    $file = writeFixture($content);

    $data = (new DataService())->getParadatafromFile($file, 'survey-a')->getData();

    expect($data)->toHaveCount(2)
        ->and($data->toArray()[0])->toMatchArray([
            'interview_number' => 1,
            'label'            => 'StartTime',
            'result'           => '2024-01-15 10:30:00',
        ])
        ->and($data->toArray()[1])->toMatchArray([
            'label'  => 'Device',
            'result' => 'Phone',
        ]);

    unlink($file);
});

it('loads an empty paradata file as an empty collection', function () {
    $file = writeFixture('');

    expect((new DataService())->getParadatafromFile($file, 'survey-a')->getData())->toHaveCount(0);

    unlink($file);
});

it('loads closed answers from file into a DataCollection', function () {
    $surveyId = 'survey-a';

    $single = Variable::create(['survey_id' => $surveyId, 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    $multi = Variable::create(['survey_id' => $surveyId, 'name' => 'Q2', 'type' => VariableTypeEnum::MULTIPLE, 'position' => 42, 'length' => 3, 'fraction' => 0]);
    $numeric = Variable::create(['survey_id' => $surveyId, 'name' => 'Q3', 'type' => VariableTypeEnum::NUMERICAL, 'position' => 45, 'length' => 3, 'fraction' => 2]);
    // OPEN is loaded from the verbatim file, never from this one
    Variable::create(['survey_id' => $surveyId, 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 50, 'length' => 5, 'fraction' => 0]);

    // cols 1-8 interview, 9-10 sub-q, 11-15 seconds, 16-19 screens, 21-28 interviewer, 29-40 datetime, then variables
    $row = '00000001' . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030' . '3' . '101' . '12345' . '     ';
    $file = writeFixture($row."\n");

    $data = (new DataService())->setSurvey($surveyId)->getClosedAnswersFromFile($file)->getData();

    expect($data)->toBeInstanceOf(DataCollection::class)
        ->and($data->getDataClass())->toBe(AnswerData::class)
        ->and($data)->toHaveCount(3);

    $byVariable = collect($data->toArray())->keyBy('variable_id');

    expect($byVariable[$single->id])->toMatchArray([
        'survey_id'        => $surveyId,
        'interview_number' => 1,
        'result'           => json_encode(['q1' => 3]),
    ]);
    expect($byVariable[$multi->id]['result'])->toBe(json_encode(['q2' => [1, 0, 1]]));
    expect($byVariable[$numeric->id]['result'])->toBe(json_encode(['q3' => 123.45]));

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
    'alpha multibyte'       => [VariableTypeEnum::ALPHA, 6, 'Café  ', 'Café'],
    'datetime'              => [VariableTypeEnum::DATETIME, 18, '2024/01/15 1030:45', '2024-01-15 10:30:45'],
    'date'                  => [VariableTypeEnum::DATE, 10, '2024/01/15', '2024-01-15'],
    'time'                  => [VariableTypeEnum::TIME, 4, '1030', '10:30:00'],
    'calculable'            => [VariableTypeEnum::CALCULABLE, 3, '123', null],
    'matrix'                => [VariableTypeEnum::MATRIX, 3, '123', null],
    'dummy'                 => [VariableTypeEnum::DUMMY, 3, '123', null],
]);

it('keeps columns after a multibyte ALPHA value in place', function () {
    $alpha = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q1', 'type' => VariableTypeEnum::ALPHA, 'position' => 41, 'length' => 4]);
    $single = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q2', 'type' => VariableTypeEnum::SINGLE, 'position' => 45, 'length' => 1]);

    $file = writeFixture(closedAnswerRow('Café' . '7' . "\r"));

    $byVariable = collect((new DataService())->getClosedAnswersFromFile($file, 'survey-a')->getData()->toArray())->keyBy('variable_id');

    expect(json_decode($byVariable[$alpha->id]['result'], true))->toBe(['q1' => 'Café'])
        ->and(json_decode($byVariable[$single->id]['result'], true))->toBe(['q2' => 7]);

    unlink($file);
});

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
    $q4 = Variable::create(['survey_id' => 'survey-a', 'name' => 'Q4', 'type' => VariableTypeEnum::OPEN, 'position' => 10, 'length' => 5]);
    Variable::create(['survey_id' => 'survey-a', 'name' => 'Q5_Other', 'type' => VariableTypeEnum::OPEN, 'position' => 20, 'length' => 5]);

    $file = writeFixture(
        "000000010100010005 Hello world\n".
        "00000002010002000597 Second answer\n"
    );

    $service = (new DataService())->getOpenAnswersFromFile($file, 'survey-a')->persist();

    expect(Sample::ofSurvey('survey-a')->count())->toBe(2)
        ->and(Answer::count())->toBe(2);

    $sample = Sample::ofSurvey('survey-a')->where('interview_number', 1)->first();
    $row = Answer::where('sample_id', $sample->id)->first();

    expect($row->variable_id)->toBe($q4->id)
        ->and($row->result)->toBe(['q4' => 'Hello world']);

    // re-persist with changed text: same rows, updated verbatim
    file_put_contents($file, "000000010100010005 Hello again\n00000002010002000597 Second answer\n");
    $service->getOpenAnswersFromFile($file, 'survey-a')->persist();

    expect(Sample::count())->toBe(2)
        ->and(Answer::count())->toBe(2)
        ->and($row->fresh()->result)->toBe(['q4' => 'Hello again']);

    unlink($file);
});

it('loads closed and open answer files in either order without one wiping the other', function () {
    $surveyId = 'survey-a';
    Variable::create(['survey_id' => $surveyId, 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    Variable::create(['survey_id' => $surveyId, 'name' => 'Q1_Other', 'type' => VariableTypeEnum::OPEN, 'position' => 42, 'length' => 5, 'fraction' => 0]);

    $closed = writeFixture('00000001' . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030' . '7' . '     ' . "\n");
    $open   = writeFixture("000000010100042005 Free text\n");

    $service = (new DataService())->setSurvey($surveyId);

    $service->getOpenAnswersFromFile($open)->persist();
    $service->getClosedAnswersFromFile($closed)->persist();

    $sample = Sample::ofSurvey($surveyId)->where('interview_number', 1)->first();

    expect(Answer::count())->toBe(2)
        ->and($sample->getAnswers())->toBe(['q1' => 7, 'q1_other' => 'Free text']);

    // and the other way round
    $service->getClosedAnswersFromFile($closed)->persist();
    $service->getOpenAnswersFromFile($open)->persist();

    expect(Answer::count())->toBe(2)
        ->and($sample->fresh()->getAnswers())->toBe(['q1' => 7, 'q1_other' => 'Free text']);

    expect((new AnswerService($surveyId))->getAnswers()[1]->pluck('answer', 'variable_name')->all())
        ->toEqualCanonicalizing(['Q1' => 7, 'Q1_Other' => 'Free text']);

    unlink($closed);
    unlink($open);
});

it('runs a fixed number of queries when loading closed answers, regardless of rows', function () {
    $surveyId = 'survey-a';
    Variable::create(['survey_id' => $surveyId, 'name' => 'Q1', 'type' => VariableTypeEnum::SINGLE, 'position' => 41, 'length' => 1, 'fraction' => 0]);
    Variable::create(['survey_id' => $surveyId, 'name' => 'Q1_Other', 'type' => VariableTypeEnum::OPEN, 'position' => 42, 'length' => 5, 'fraction' => 0]);

    $header = fn (string $interview) => $interview . '01' . '00120' . '0005' . ' ' . 'INT00001' . '202401151030';
    $file = writeFixture(implode('', array_map(fn ($i) => $header(sprintf('%08d', $i)) . "1     \n", range(1, 50))));

    \Illuminate\Support\Facades\DB::enableQueryLog();
    (new DataService())->getClosedAnswersFromFile($file, $surveyId);

    // one query: the survey's variables
    expect(\Illuminate\Support\Facades\DB::getQueryLog())->toHaveCount(1);

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
