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
use Carbon\Exceptions\InvalidFormatException;
use Nikoleesg\Survey\Models\Variable;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Data\ParadataData;
use Nikoleesg\Survey\Data\SampleData;
use Nikoleesg\Survey\Data\AnswerData;
use Nikoleesg\Survey\Exceptions\MissingSurveyIdException;
use Nikoleesg\Survey\Exceptions\NoDataLoadedException;
use Nikoleesg\Survey\Exceptions\UnparseableColumnException;
use Nikoleesg\Survey\Exceptions\UnreadableFileException;

class DataService implements Arrayable
{
    protected ?string $surveyId = null;

    protected DataCollection $data;

    /**
     * The interview headers of the last closed answer file loaded, one
     * SampleData per record; null after any other loader.
     */
    protected ?DataCollection $samples = null;

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
     * @throws UnparseableColumnException
     */
    public function getClosedAnswersFromFile(string $fileName, ?string $surveyId = null): self
    {
        $surveyId = $this->resolveSurveyId($surveyId);

        $content = $this->openFile($fileName, __FUNCTION__);

        $result  = [];
        $samples = [];

        // OPEN variables are owned by getOpenAnswersFromFile(); emitting a
        // row for them here would let a later closed load wipe the verbatim
        $variables = Variable::query()
            ->active()
            ->ofSurvey($surveyId)
            ->whereNot('type', VariableTypeEnum::OPEN)
            ->get();

        while (!$content->eof()) {

            if (Str::length($row = $content->fgets()) > 1) {

                // only the line ending goes: dropping any other byte would
                // shift every fixed-width column after it
                $string = rtrim($row, "\r\n");

                // columns 1-60 are the system header: the sample row
                $sample = SampleData::fromRow($surveyId, $string);

                // a repeated interview number keeps the last record, as the
                // upsert would
                $samples[$sample->interview_number] = $sample;

                $variableAnswers = $this->getVariableAnswers($surveyId, $sample->interview_number, $variables, $string);

                // append sample's answer to result
                foreach ($variableAnswers as $variableAnswer) {
                    $result[] = $variableAnswer;
                }
            }
        }

        $this->data    = AnswerData::collect($result, DataCollection::class);
        $this->samples = SampleData::collect(array_values($samples), DataCollection::class);

        return $this;
    }

    /**
     * @param Collection<int, Variable> $variables
     *
     * @throws UnparseableColumnException
     */
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

            // TODO: calculation, dummy
            $data = match ($variable->type) {
                // excluded by the query above; loaded from the verbatim file instead
                VariableTypeEnum::OPEN => throw new \LogicException("OPEN variable \"{$variable->name}\" has no closed answer column"),
                VariableTypeEnum::CALCULABLE => null,
                VariableTypeEnum::MATRIX => null, // TODO: matrix answers
                VariableTypeEnum::DUMMY => null,
                // an all-blank column means not asked / not answered, never code 0
                default => trim($contentOfColumns) === '' ? null : match ($variable->type) {
                    VariableTypeEnum::SINGLE => (int)$contentOfColumns,
                    VariableTypeEnum::MULTIPLE => array_map('intval', str_split($contentOfColumns)),
                    VariableTypeEnum::NUMERICAL => $fraction > 0 ? (int)$contentOfColumns / pow(10, $fraction) : (int)$contentOfColumns,
                    VariableTypeEnum::ALPHA => rtrim($contentOfColumns),
                    VariableTypeEnum::DATETIME => $this->parseDateColumn($interviewNumber, $variable, 'datetime', $contentOfColumns)->toDateTimeString(),
                    VariableTypeEnum::DATE => $this->parseDateColumn($interviewNumber, $variable, 'date', $contentOfColumns)->toDateString(),
                    VariableTypeEnum::TIME => $this->parseDateColumn($interviewNumber, $variable, 'time', $contentOfColumns)->toTimeString(),
                },
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
     * Parse a non-blank date/time column with the configured survey.formats.{$format}.
     *
     * @throws UnparseableColumnException
     */
    protected function parseDateColumn(int $interviewNumber, Variable $variable, string $format, string $content): Carbon
    {
        $pattern = config("survey.formats.$format");

        try {
            $date = Carbon::createFromFormat($pattern, trim($content));
        } catch (InvalidFormatException) {
            $date = false;
        }

        if ($date === false) {
            throw UnparseableColumnException::make($interviewNumber, $variable, $pattern, $content);
        }

        return $date;
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

        $this->data    = ParadataData::collect($result, DataCollection::class);
        $this->samples = null;

        return $this;
    }

    /**
     * Load the verbatim file as answers of the survey's OPEN variables. Every
     * open-end is its own variable (Q5_Other, Q5_97_Other), so a file row maps
     * to one (interview, variable) by position/length and its code number is
     * already implied by the parent question's closed answer. Rows that match
     * no active OPEN variable are ignored: the question is optional and the
     * file is not validated here.
     *
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

        $variables = Variable::query()
            ->active()
            ->ofSurvey($surveyId)
            ->where('type', VariableTypeEnum::OPEN)
            ->get();

        $byColumns = $variables->keyBy(fn (Variable $variable) => "{$variable->position}:{$variable->length}");
        $byId      = $variables->keyBy('id');

        // interview => variable id => code number => verbatim
        $verbatims = [];

        while (!$content->eof()) {

            if (Str::length($row = $content->fgets()) > 1) {

                $openAnswer = $this->parseOpenAnswerString($row);

                $variable = $byColumns->get("{$openAnswer['position']}:{$openAnswer['length']}");

                // no variable at these columns, or nothing was typed: not an answer
                if ($variable === null || $openAnswer['verbatim_text'] === '') {
                    continue;
                }

                $verbatims[$openAnswer['interview_number']][$variable->id][$openAnswer['code_number']] = $openAnswer['verbatim_text'];
            }
        }

        $result = [];

        foreach ($verbatims as $interviewNumber => $byVariable) {
            foreach ($byVariable as $variableId => $byCode) {
                // one row is the norm; several coded rows for one variable are
                // kept apart by code number rather than overwriting each other
                $data = count($byCode) === 1 ? reset($byCode) : collect($byCode)->sortKeys()->all();

                $result[] = [
                    'survey_id'        => $surveyId,
                    'variable_id'      => $variableId,
                    'interview_number' => $interviewNumber,
                    'result'           => [$byId[$variableId]->slug => $data],
                ];
            }
        }

        $this->data    = AnswerData::collect($result, DataCollection::class);
        $this->samples = null;

        return $this;
    }

    /**
     * One line of the verbatim file: an ASCII header of fixed columns
     * (interview 8, sub-questionnaire 2, position 5, length 3, code number up
     * to the first space) followed by the verbatim text. The header is read
     * by byte offset and the text is handed back as-is apart from surrounding
     * whitespace, so multibyte characters and inner spacing survive.
     *
     * @return array{interview_number: int, sub_questionnaire_number: int, position: int, length: int, code_number: int, verbatim_text: string}
     */
    public function parseOpenAnswerString(string $row): array
    {
        $row = rtrim($row, "\r\n");

        $separator = strpos($row, ' ', 18);

        return [
            'interview_number'         => intval(substr($row, 0, 8)),
            'sub_questionnaire_number' => intval(substr($row, 8, 2)),
            'position'                 => intval(substr($row, 10, 5)),
            'length'                   => intval(substr($row, 15, 3)),
            // blank means a pure open question, not an "other, specify" of a code
            'code_number'              => intval(substr($row, 18, $separator === false ? null : $separator - 18)),
            'verbatim_text'            => $separator === false ? '' : trim(substr($row, $separator + 1)),
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
        $fileHandle = @fopen($fileName, 'rb');

        if ($fileHandle === false) {
            throw UnreadableFileException::make($fileName, $loader);
        }

        // Skip a UTF-16 BOM (LE or BE) if present; otherwise read from byte 0.
        // Reading raw bytes with fgets would stop in the middle of a UTF-16
        // code unit, so only ever look at the first two bytes here.
        $bom = fread($fileHandle, 2);

        if ($bom !== "\xFF\xFE" && $bom !== "\xFE\xFF") {
            rewind($fileHandle);
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
     * The closed answer file also carries the interview header, which is
     * upserted onto the samples first, so it fills in stubs and refreshes on
     * reload. Closed and open answers share the answers table but never the
     * same (sample, variable) row, so either file can be reloaded on its own.
     *
     * @throws NoDataLoadedException
     */
    public function persist(): self
    {
        $dataModel = match ($this->getData()->getDataClass()) {
            AnswerData::class   => config('survey.closed_answer_model'),
            ParadataData::class => config('survey.paradata_model'),
            default             => throw new \LogicException('No model persists ' . $this->getData()->getDataClass()),
        };

        $this->persistSamples();

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
     * Upsert the interview headers of a loaded closed answer file onto the
     * samples table; a no-op after the other loaders.
     */
    protected function persistSamples(): void
    {
        $rows = collect($this->getSamples()->toArray());

        if ($rows->isEmpty()) {
            return;
        }

        $sampleModel = config('survey.sample_model');

        $uniqueBy   = $sampleModel::UPSERT_KEYS;
        $updateKeys = array_values(array_diff(array_keys($rows->first()), $uniqueBy));

        foreach ($rows->chunk(config('survey.persist_chunk_size')) as $chunk) {
            $sampleModel::upsert($chunk->values()->all(), $uniqueBy, $updateKeys);
        }
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

    /**
     * The interview headers parsed by the last getClosedAnswersFromFile();
     * empty when the last load was another file.
     */
    public function getSamples(): DataCollection
    {
        return $this->samples ?? SampleData::collect([], DataCollection::class);
    }

    public function toArray(): array
    {
        return isset($this->data) ? $this->data->toArray() : [];
    }
}
