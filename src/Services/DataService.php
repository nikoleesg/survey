<?php

namespace Nikoleesg\Survey\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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
use Nikoleesg\Survey\Exceptions\MissingSurveyIdException;
use Nikoleesg\Survey\Exceptions\NoDataLoadedException;
use Nikoleesg\Survey\Exceptions\UnreadableFileException;

class DataService implements Arrayable
{
    protected ?string $surveyId = null;

    protected DataCollection $data;

    public function setSurvey(?string $surveyId): self
    {
        $this->surveyId = $surveyId;

        return $this;
    }

    /**
     * Explicit argument, then setSurvey(), then the configured default.
     *
     * @throws MissingSurveyIdException
     */
    protected function resolveSurveyId(?string $surveyId): string
    {
        $surveyId = $surveyId ?? $this->surveyId ?? config('survey.survey_id');

        if ($surveyId === null || $surveyId === '') {
            throw MissingSurveyIdException::make();
        }

        return $surveyId;
    }

    /**
     * @throws UnreadableFileException
     */
    protected function openFile(string $fileName, string $loader): SplFileObject
    {
        if (!is_file($fileName) || !is_readable($fileName)) {
            throw UnreadableFileException::make($fileName, $loader);
        }

        return new SplFileObject($fileName);
    }

    /**
     * @param string $fileName
     * @param string|null $surveyId
     * @return $this
     *
     * @throws MissingSurveyIdException
     * @throws UnreadableFileException
     */
    public function getClosedAnswersFromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $this->resolveSurveyId($surveyId);

        $content = $this->openFile($fileName, __FUNCTION__);

        $result = [];

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

                // append sample's answer to result
                foreach ($variableAnswers as $variableAnswer) {
                    $result[] = $variableAnswer;
                }
            }
        }

        $this->data = AnswerData::collect($result, DataCollection::class);

        return $this;
    }

    protected function getVariableAnswers(string $surveyId, int $interviewNumber, Collection $variables, string $string): DataCollection
    {
        $answers = [];

        foreach ($variables as $variable) {

            // get content from columns
            $startPosition = (int)$variable->position;
            $length        = (int)$variable->length;
            $fraction      = (int)$variable->fraction;

            $contentOfColumns = Str::substr($string, $startPosition - 1, $length + $fraction);

            // map content to result

            // TODO: load open answer; load multiple open answer for multiple answer; calculation, dummy
            $data = match ($variable->type) {
                VariableTypeEnum::SINGLE => (int)$contentOfColumns,
                VariableTypeEnum::MULTIPLE => array_map('intval', str_split($contentOfColumns)),
                VariableTypeEnum::NUMERICAL => $fraction > 0 ? (int)$contentOfColumns / pow(10, $fraction) : (int)$contentOfColumns,
                VariableTypeEnum::OPEN => $this->getVerbatimText($surveyId, $interviewNumber, $startPosition, $length),
                VariableTypeEnum::ALPHA => $contentOfColumns,
                VariableTypeEnum::CALCULABLE => null,
                VariableTypeEnum::MATRIX => null, // TODO: matrix answers
                VariableTypeEnum::DUMMY => null,
                VariableTypeEnum::DATETIME => Carbon::createFromFormat('Y/m/d Hi:s', $contentOfColumns)->toDateTimeString(),
                VariableTypeEnum::DATE => Carbon::createFromFormat('Y/m/d Hi:s', $contentOfColumns)->toDateString(),
                VariableTypeEnum::TIME => Carbon::createFromFormat('Hi', $contentOfColumns)->toTimeString(),
            };

            // answer keyed by variable slug; MULTIPLE is a list of 0/1 per code
            $result = [$variable->slug => $data];

            $answerData = [
                'survey_id'        => $surveyId,
                'variable_id'      => $variable->id,
                'interview_number' => $interviewNumber,
                'result'           => $result,
            ];

            $answers[] = $answerData;
        }

        return AnswerData::collect($answers, DataCollection::class);
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
        return OpenAnswer::query()
            ->whereHas('sample', fn ($query) => $query
                ->ofSurvey($surveyId)
                ->where('interview_number', $interviewNumber))
            ->where('position', $position)
            ->where('length', $length)
            ->first()?->verbatim_text;
    }


    /**
     * @param string $fileName
     * @param string|null $surveyId
     * @return $this
     *
     * @throws MissingSurveyIdException
     * @throws UnreadableFileException
     */
    public function getParadatafromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $this->resolveSurveyId($surveyId);

        $csvContent = $this->getCsvFileContent($fileName, __FUNCTION__);

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
                    'interview_number' => (int)$match[1],
                    // Convert the first column to an integer
                    'label'            => $match[2],
                    'result'           => str_replace(["\r",
                        "\n"], '', $match[3]),
                ];

                $result[] = array_merge($paraData, [
                    'survey_id'    => $surveyId
                ]);
            }
        }

        $this->data = ParadataData::collect($result, DataCollection::class);

        return $this;
    }

    /**
     * @param string $fileName
     * @param string|null $surveyId
     * @return $this
     *
     * @throws MissingSurveyIdException
     * @throws UnreadableFileException
     */
    public function getOpenAnswersFromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $this->resolveSurveyId($surveyId);

        $content = $this->openFile($fileName, __FUNCTION__);

        $result = [];

        while (!$content->eof()) {

            if (Str::length($row = $content->fgets()) > 1) {

                $openAnswer = $this->parseOpenAnswerString($row);

                $result[] = array_merge($openAnswer, [
                    'survey_id'       => $surveyId,
                ]);
            }
        }

        $this->data = OpenAnswerData::collect($result, DataCollection::class);

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
            // column is NOT NULL (part of the unique key); blank means 0
            'code_number'              => intval($posNineteen),
            'verbatim_text'            => Str::after($string, ' ')
        ];
    }

    /**
     * @param string $fileName
     * @param string $loader
     * @param string $encode
     * @return string
     *
     * @throws UnreadableFileException
     */
    protected function getCsvFileContent(string $fileName, string $loader, string $encode = 'UTF-16LE'): string
    {
        // Open the file with read-only access
        $fileHandle = @fopen($fileName, 'r');

        if ($fileHandle === false) {
            throw UnreadableFileException::make($fileName, $loader);
        }

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

    /**
     * Upsert the loaded rows. Rows carry (survey_id, interview_number); these
     * are resolved to a sample_id first, creating stub samples as needed, so
     * the open answer / paradata files can be loaded before the closed answers.
     *
     * @throws NoDataLoadedException
     */
    public function persist(): self
    {
        $dataModel = match ($this->getData()->getDataClass()) {
            AnswerData::class     => config('survey.closed_answer_model'),
            OpenAnswerData::class => config('survey.open_answer_model'),
            ParadataData::class   => config('survey.paradata_model'),
        };

        $rows = collect($this->getData()->toArray());

        if ($rows->isEmpty()) {
            return $this;
        }

        $sampleIds = $this->resolveSampleIds($rows);

        $rows = $rows->map(fn (array $row) => [
            'sample_id' => $sampleIds[$row['survey_id']][$row['interview_number']],
            ...Arr::except($row, ['survey_id', 'interview_number']),
        ]);

        $uniqueBy   = $dataModel::UPSERT_KEYS;
        $upsertKeys = array_values(array_diff(array_keys($rows->first()), $uniqueBy));

        foreach ($rows->chunk(config('survey.persist_chunk_size')) as $chunk) {
            $dataModel::upsert($chunk->values()->all(), $uniqueBy, $upsertKeys);
        }

        return $this;
    }

    /**
     * Map every (survey_id, interview_number) pair in $rows to a sample id,
     * inserting samples that do not exist yet.
     *
     * @return array<string, array<int, int>> survey_id => [interview_number => sample id]
     */
    protected function resolveSampleIds(SupportCollection $rows): array
    {
        $sampleModel = config('survey.sample_model');

        $sampleIds = [];

        foreach ($rows->groupBy('survey_id') as $surveyId => $group) {

            $interviewNumbers = $group->pluck('interview_number')->unique()->values();

            $existing = $sampleModel::query()
                ->ofSurvey($surveyId)
                ->whereIn('interview_number', $interviewNumbers)
                ->pluck('id', 'interview_number');

            $missing = $interviewNumbers
                ->reject(fn (int $number) => $existing->has($number))
                ->map(fn (int $number) => ['survey_id' => $surveyId, 'interview_number' => $number]);

            foreach ($missing->chunk(config('survey.persist_chunk_size')) as $chunk) {
                $sampleModel::insert($this->withTimestamps($chunk->values()->all()));
            }

            $sampleIds[$surveyId] = $sampleModel::query()
                ->ofSurvey($surveyId)
                ->whereIn('interview_number', $interviewNumbers)
                ->pluck('id', 'interview_number')
                ->all();
        }

        return $sampleIds;
    }

    /**
     * Model::insert() bypasses Eloquent timestamps; add them by hand.
     */
    protected function withTimestamps(array $rows): array
    {
        $now = Carbon::now();

        return array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $rows);
    }

    /**
     * @throws NoDataLoadedException
     */
    public function getData(): DataCollection
    {
        if (!isset($this->data)) {
            throw NoDataLoadedException::make();
        }

        return $this->data;
    }

    public function toArray(): array
    {
        return isset($this->data) ? $this->data->toArray() : [];
    }
}
