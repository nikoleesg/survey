<?php

namespace Nikoleesg\Survey\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\DataCollection;
use SplFileObject;
use Carbon\Carbon;
use Nikoleesg\Survey\Models\Variable;
use Nikoleesg\Survey\Models\OpenAnswer;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Data\OpenAnswerData;
use Nikoleesg\Survey\Data\ParadataData;
use Nikoleesg\Survey\Data\ClosedAnswerData;
use Nikoleesg\Survey\Data\AnswerData;

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
    public function getClosedAnswersFromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $surveyId ?? $this->surveyId ?? null;

        if ($surveyId === null) {
            // throw exception
            return $this;
        }

        $filePath = Storage::path($fileName);

        $content = new SplFileObject($filePath);

        $result = [];

        // get Survey Id
        $surveyId = $this->surveyId;

        // get Variables of survey (active)
        $variables = Variable::query()->active()->ofSurvey($surveyId)->get();

        while (!$content->eof()) {

            if (Str::length($row = $content->fgets()) > 1) {

                $string = preg_replace('/[[:^print:]]/', '', $row);

                // parse closed answer of system variables
                $closedAnswer = ClosedAnswerData::from($string);

                // Parse answers of variables...
                $interviewNumber = $closedAnswer->interview_number;

                $variableAnswers = $this->getVariableAnswers($surveyId, $interviewNumber, $variables, $string);

                Log::debug('{answerCnt} answers loaded for {interviewNumber}.', [
                    'answerCnt'       => $variableAnswers->count(),
                    'interviewNumber' => $interviewNumber,
                    'answers'         => $variableAnswers->toJson()
                ]);

                // append sample's answer to result
                foreach ($variableAnswers as $variableAnswer) {
                    $result[] = $variableAnswer;
                }
            }
        }

        Log::debug('{numOfAnswer} answers loaded.', [
            'numOfAnswer' => count($result),
            'result' => $result
        ]);

        $this->data = AnswerData::collection($result);

        return $this;
    }

    protected function getVariableAnswers(string $surveyId, int $interviewNumber, Collection $variables, string $string): DataCollection
    {
        $answers = [];

        Log::debug('Get variables answer from raw data.', [
            'survey_id'        => $surveyId,
            'interview_number' => $interviewNumber,
            'variables'        => $variables->toJson(),
            'closed_answer'    => $string
        ]);

        foreach ($variables as $variable) {

            // get content from columns
            $startPosition = (int)$variable->position;
            $length        = (int)$variable->length;
            $fraction      = (int)$variable->fraction;

            $contentOfColumns = Str::substr($string, $startPosition - 1, $length + $fraction);

            Log::debug('Extract content from closed answer: {contentOfColumns}', [
                'contentOfColumns' => $contentOfColumns,
                'position'         => $startPosition,
                'length'           => $length,
                'fraction'         => $fraction
            ]);

            // map content to result

            // TODO: load open answer; load multiple open answer for multiple answer; calculation, dummy
            $data = match ($variable->type) {
                VariableTypeEnum::SINGLE     => (int)$contentOfColumns,
                VariableTypeEnum::MULTIPLE   => str_split($contentOfColumns),
                VariableTypeEnum::NUMERICAL  => $fraction > 0 ? (int)$contentOfColumns / pow(10, $fraction) : (int)$contentOfColumns,
                VariableTypeEnum::OPEN       => $this->getVerbatimText($surveyId, $interviewNumber, $startPosition, $length),
                VariableTypeEnum::ALPHA      => $contentOfColumns,
                VariableTypeEnum::CALCULABLE => null,
                VariableTypeEnum::DUMMY      => null,
                VariableTypeEnum::DATETIME   => Carbon::createFromFormat('Y/m/d Hi:s', $contentOfColumns)->toDateTimeString(),
                VariableTypeEnum::DATE       => Carbon::createFromFormat('Y/m/d Hi:s', $contentOfColumns)->toDateString(),
                VariableTypeEnum::TIME       => Carbon::createFromFormat('Hi', $contentOfColumns)->toTimeString(),
            };

            $result = [];

            foreach (!is_array($data) ? [$data] : $data as $key => $item) {
                // add result, variable name as key, answer as value
                $variable_name = $variable->type === VariableTypeEnum::MULTIPLE ? $variable->slug.'_'.$key : $variable->slug;

                $result = Arr::add($result, $variable_name, $item);

            }

            $answerData = [
                'survey_id'        => $surveyId,
                'variable_id'      => $variable->id,
                'interview_number' => $interviewNumber,
                'result'           => $result,
            ];

            $answer = array_merge($answerData,[
                'answer_md5' => $this->md5hash($answerData, ['variable_id', 'interview_number'])
            ]);

            Log::debug('Answer of {variable} for {interviewNumber} is: {answer}', [
                'variable'        => $variable->name,
                'interviewNumber' => $interviewNumber,
                'answer'          => json_encode($answer)
            ]);

            array_push($answers, $answer);
        }

        return AnswerData::collection($answers);
    }

    /**
     * @param string $surveyId
     * @param string $interviewNumber
     * @param int $position
     * @param int $length
     * @return string|null
     */
    protected function getVerbatimText(string $surveyId, string $interviewNumber, int $position, int $length): ?string
    {
        return OpenAnswer::query()->where([
            'survey_id'        => $surveyId,
            'interview_number' => $interviewNumber,
            'position'         => $position,
            'length'           => $length
        ])->first()?->verbatim_text;
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
            return $this;
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
            return $this;
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
