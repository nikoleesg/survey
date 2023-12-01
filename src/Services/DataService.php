<?php

namespace Nikoleesg\Survey\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\DataCollection;
use SplFileObject;
use Nikoleesg\Survey\Data\OpenAnswerData;

class DataService
{
    public function persistOpenAnswersFromFile(string $fileName, string $surveyName)
    {
        $openAnswerCollection = $this->getOpenAnswersFromFile($fileName, $surveyName);

        $openAnswerModel = config('survey.open_answer_model');

        $rawData = collect($openAnswerCollection->toArray())
            ->map(function ($item) {
                return array_merge($item, ['uuid' => Str::orderedUuid()]);
            });

        $openAnswerModel::upsert($rawData->toArray(), ['open_answer_md5'], ['verbatim_text']);
    }

    /**
     * @param string $fileName
     * @param string $surveyName
     * @return DataCollection
     */
    public function getOpenAnswersFromFile(string $fileName, string $surveyName): DataCollection
    {
        $filePath = Storage::path($fileName);

        $content = new SplFileObject($filePath);

        $rows = [];

        while (!$content->eof()) {

            if (Str::length($row = $content->fgets()) > 1) {

                $openAnswer = $this->getOpenAnswerFromString($row);

                $rows[] = array_merge($openAnswer, [
                    'survey_name' => $surveyName,
                    'open_answer_md5' => $this->getOpenAnswerMd5($openAnswer)
                ]);
            }
        }

        return OpenAnswerData::collection($rows);
    }

    /**
     * @param string $row
     * @return array
     */
    public function getOpenAnswerFromString(string $row): array
    {
        $string = Str::squish(preg_replace('/[[:^print:]]/', '', $row));

        $fields = Str::before($string, ' ');

        $posNineteen = Str::substr($fields, 18, Str::length($fields) - 18);

        return [
            'interview_number'         => intval(Str::substr($fields, 0, 8)),
            'sub_questionnaire_number' => intval(Str::substr($fields, 8, 2)),
            'position'                 => intval(Str::substr($fields, 10, 5)),
            'length'                   => intval(Str::substr($fields, 15, 3)),
            'code_number'              => $posNineteen === "" ? null : intval($posNineteen),
            'verbatim_text'            => Str::after($string, ' ')
        ];
    }

    /**
     * @param array $openAnswer
     * @return string
     */
    protected function getOpenAnswerMd5(array $openAnswer): string
    {
        $fields = ['interview_number', 'sub_questionnaire_number', 'position', 'length', 'code_number', 'survey_name'];

        $index = Arr::join(array_values(Arr::only($openAnswer, $fields)), '_');

        return md5($index);
    }
}
