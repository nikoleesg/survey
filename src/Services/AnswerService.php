<?php

namespace Nikoleesg\Survey\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Carbon\Carbon;
use Nikoleesg\Survey\Models\Answer;
use Nikoleesg\Survey\Models\Paradata;
use Nikoleesg\Survey\Models\Sample;
use Nikoleesg\Survey\Models\Variable;
use Nikoleesg\Survey\Enums\VariableTypeEnum;
use Nikoleesg\Survey\Exceptions\MissingSurveyIdException;

class AnswerService
{
    protected ?string $surveyId = null;

    protected array|null $interviewId;

    /** @var class-string<Answer> */
    protected string $closedAnswerModel;

    /** @var class-string<Paradata> */
    protected string $paradataAnswerModel;

    public function __construct(?string $surveyId = null, int|array|null $interviewId = null)
    {
        if (!is_null($surveyId)) {
            $this->surveyId = $surveyId;
        }

        if (!is_null($interviewId)) {
            $this->setInterview($interviewId);
        }

        $this->closedAnswerModel = Answer::modelClass();

        $this->paradataAnswerModel = Paradata::modelClass();
    }

    public function setSurvey(?string $surveyId): self
    {
        $this->surveyId = $surveyId;

        return $this;
    }

    /**
     * setSurvey() / constructor argument, then the configured default.
     *
     * @throws MissingSurveyIdException
     */
    protected function resolveSurveyId(): string
    {
        $surveyId = $this->surveyId ?? config('survey.survey_id');

        if ($surveyId === null || $surveyId === '') {
            throw MissingSurveyIdException::make();
        }

        return $surveyId;
    }

    /**
     * @throws InvalidArgumentException when given anything but an int or a list of ints
     */
    public function setInterview(int|array $interviewId): self
    {
        if (is_int($interviewId)) {
            $interviewId = [$interviewId];
        }

        if (!$this->isIntegerList($interviewId)) {
            throw new InvalidArgumentException('Interview id must be an integer or a list of integers.');
        }

        $this->interviewId = $interviewId;

        return $this;
    }

    /**
     * Answers of the resolved survey, grouped by interview number. Only
     * variables of that survey are considered, so a variable id or
     * collection from another survey yields nothing.
     *
     * @throws MissingSurveyIdException
     */
    public function getAnswers(int|array|null $filteredInterview = null, array|EloquentCollection|null $filteredVariable = null): Collection
    {
        $surveyId = $this->resolveSurveyId();

        // prepare variables collection
        if ($filteredVariable instanceof EloquentCollection) {
            $variables = $filteredVariable;
        } else {
            $variableQuery = Variable::query()->ofSurvey($surveyId);

            $variableQuery->when($this->isIntegerList($filteredVariable), function (Builder $query) use ($filteredVariable) {
                return $query->whereIn('id', $filteredVariable);
            });

            $variables = $variableQuery->get();
        }

        // whereBelongsTo() rejects an empty collection; no variables means no answers
        if ($variables->isEmpty()) {
            return new Collection();
        }

        // set interview
        if (is_int($filteredInterview) || $this->isIntegerList($filteredInterview)) {
            $this->setInterview($filteredInterview);
        }

        // query
        $builder = $this->closedAnswerModel::query()->with(['variable', 'sample']);

        $builder->whereHas('sample', function (Builder $query) use ($surveyId) {
            $query->ofSurvey($surveyId)
                ->when(isset($this->interviewId), fn (Builder $q) => $q->whereIn('interview_number', $this->interviewId));
        });

        return $builder
            ->whereBelongsTo($variables, 'variable')
            ->get()
            ->map(function ($item) {

                $item['interview_number'] = $item->sample->interview_number;
                $item['variable_id']   = $item->variable->id;
                $item['variable_name'] = $item->variable->name;
                $item['variable_type'] = $item->variable->type;

                $result = Arr::get($item->result, $item->variable->slug, null);

                switch ($item->variable->type) {
                    case VariableTypeEnum::OPEN:
                    case VariableTypeEnum::ALPHA:
                        // an open answer keyed by code number is squished per entry
                        $answer = match (true) {
                            $result === null  => null,
                            is_array($result) => array_map(Str::squish(...), $result),
                            default           => Str::squish($result),
                        };
                        break;
                    case VariableTypeEnum::DATETIME:
                        $answer = $result === null ? null : Carbon::parse($result, config('survey.timezone') ?? config('app.timezone'));
                        break;
                    default:
                        $answer = $result;
                }

                $item['answer'] = $answer;

                return $item->only(['interview_number', 'variable_id', 'variable_name', 'variable_type', 'answer']);
            })
            ->groupBy('interview_number');

    }

    public function getAnswer(int $interviewId, int $variableId)
    {
        $answer = Arr::get($this->getAnswers([$interviewId], [$variableId]), $interviewId, null);

        if (is_null($answer)) {
            return null;
        }

        return $answer->first();
    }

    protected function isIntegerList($array): bool
    {
        if (!is_array($array) || !Arr::isList($array)) {
            return false;
        }

        return count(array_filter($array, 'is_int')) === count($array);
    }


}
