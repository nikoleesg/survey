<?php

use Nikoleesg\Survey\Data\AnswerData;
use Nikoleesg\Survey\Data\OpenAnswerData;
use Nikoleesg\Survey\Data\ParadataData;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Models\OpenAnswer;
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
        'code_number'              => null,
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
