<?php

namespace Nikoleesg\Survey\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\DataCollection;
use SplFileObject;
use Nikoleesg\Survey\Data\OpenAnswerData;
use Nikoleesg\Survey\Data\ParadataData;

class DataService implements Arrayable
{
    protected string $surveyId;

    protected DataCollection $data;


    public function setSurvey(string $surveyId): self
    {
        $this->surveyId = $surveyId;

        return $this;
    }

    /**
     * @param string $fileName
     * @param string|null $surveyId
     * @return $this
     */
    public function getParadatafromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $surveyId ?? $this->surveyId ?? null;

        if ($surveyId === null) {
            // throw exception
            return false;
        }

        $csvContent = $this->getCsvFileContent($fileName);

        // Regular expression pattern
        $pattern = '/^(\d{8})\t([^\t]*)\t(.*?)\R*$/m';

        // Initialize an array to store the results
        $result = [];

        // Use preg_match_all to find matches
        $numMatches = preg_match_all($pattern, $csvContent, $matches, PREG_SET_ORDER);

        // Check if any matches were found
        if ($numMatches > 0) {
            // Extract data from matches
            foreach ($matches as $match) {

                $paraData = [
                    'interview_number' => (int)$match[1],  // Convert the first column to an integer
                    'label'            => $match[2],
                    'result'           => str_replace(["\r", "\n"], '', $match[3]),
                ];

                $result[] = array_merge($paraData, [
                    'survey_id'    => $surveyId,
                    'paradata_md5' => $this->md5hash($paraData, ['interview_number', 'label'])
                ]);
            }
        }

        $this->data = ParadataData::collection($result);

        return $this;
    }

    /**
     * @param string $fileName
     * @param string|null $surveyId
     * @return $this
     */
    public function getOpenAnswersFromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $surveyId ?? $this->surveyId ?? null;

        if ($surveyId === null) {
            // throw exception
            return false;
        }

        $filePath = Storage::path($fileName);

        $content = new SplFileObject($filePath);

        $result = [];

        while (!$content->eof()) {

            if (Str::length($row = $content->fgets()) > 1) {

                $openAnswer = $this->parseOpenAnswerString($row);

                $result[] = array_merge($openAnswer, [
                    'survey_id'       => $surveyId,
                    'open_answer_md5' => $this->md5hash($openAnswer, ['interview_number', 'sub_questionnaire_number', 'position', 'length', 'code_number'])
                ]);
            }
        }

        $this->data = OpenAnswerData::collection($result);

        return $this;
    }

    /**
     * @param string $row
     * @return array
     */
    public function parseOpenAnswerString(string $row): array
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
     * @param array $data
     * @param array $fields
     * @return string
     */
    protected function md5hash(array $data, array $fields): string
    {
        return md5(Arr::join(array_values(Arr::only($data, $fields)), '_') . '_' . $this->surveyId);
    }

    /**
     * @param string $fileName
     * @param string $encode
     * @return string
     */
    protected function getCsvFileContent(string $fileName, string $encode = 'UTF-16LE'): string
    {
        $filePath = Storage::path($fileName);

        // Open the file with read-only access
        $fileHandle = fopen($filePath, 'r');

        $firstLine = fgets($fileHandle);

        if (str_starts_with($firstLine, "\xFF\xFE")) {
            // BOM detected, skip the first two bytes
            fseek($fileHandle, 2);
        }

        // Add a stream filter to convert UTF-16 LE to UTF-8
        stream_filter_append($fileHandle, "convert.iconv.$encode/UTF-8");

        // Read the content of the file
        $fileContent = stream_get_contents($fileHandle);

        // Close the file handle
        fclose($fileHandle);

        return $fileContent;
    }

    public function persist()
    {
        if (!$this->data instanceof DataCollection) {
            // TODO: throw exception
        }

        $dataModel = match ($this->getData()->getDataClass()) {
            'Nikoleesg\Survey\Data\OpenAnswerData' => config('survey.open_answer_model'),
            'Nikoleesg\Survey\Data\ParadataData' => config('survey.paradata_model'),
        };

        $data = collect($this->getData()->toArray())
            ->map(function ($item, $key) {
                return array_merge($item, ['uuid' => Str::orderedUuid()]);
            });

        if ($data->count() == 0) {
            // No data to persist
            return $this;
        }

        // md5 hash column
        $dataKeys = array_keys($data->first());

        $uniqueKey = Arr::first($dataKeys, function ($item, $key) {
            return Str::endsWith($item, '_md5');
        });

        // update columns
        $upsertKeys = Arr::except($dataKeys, [$uniqueKey, 'uuid', 'id']);

        $dataModel::upsert($data->toArray(), [$uniqueKey], $upsertKeys);

        return $this;
    }

    public function getData(): DataCollection
    {
        return $this->data;
    }

    public function toArray(): array
    {
        // TODO: Implement toArray() method.
        return $this->data instanceof DataCollection ? $this->data->toArray() : [];
    }
}
