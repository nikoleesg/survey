<?php

namespace Nikoleesg\Survey\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Nikoleesg\Survey\Models\Variable;
use Nikoleesg\Survey\Enums\VariableTypeEnum;


class AnswerService
{
    protected string $surveyId;

    protected array|null $interviewId;

    protected string $closedAnswerModel;

    protected string $openAnswerModel;

    protected string $paradataAnswerModel;

    public function __construct(?string $surveyId = null, int|array|null $interviewId = null)
    {
        if (!is_null($surveyId)) {
            $this->surveyId = $surveyId;
        }

        if (!is_null($interviewId)) {
            $this->setInterview($interviewId);
        }

        $this->closedAnswerModel = config('survey.closed_answer_model');

        $this->openAnswerModel = config('survey.open_answer_model');

        $this->paradataAnswerModel = config('survey.paradata_model');
    }

    public function setSurvey(string $surveyId): self
    {
        $this->surveyId = $surveyId;

        return $this;
    }

    public function setInterview(int|array $interviewId): self
    {
        if (is_int($interviewId)) {
            $this->interviewId = [$interviewId];
        }

        if ($this->isIntegerList($interviewId)) {
            $this->interviewId = $interviewId;
        }

        return $this;
    }

    public function getAnswers(array|EloquentCollection|null $filteredVariable = null, int|array|null $filteredInterview = null)
    {
        if (is_null($this->surveyId)) {
            // TODO: exception
        }

        // prepare variables collection
        if ($filteredVariable instanceof EloquentCollection) {
            $variables = $filteredVariable;
        } else {
            $variableQuery = Variable::query();

            $variableQuery->when($this->isIntegerList($filteredVariable), function (Builder $query) use ($filteredVariable) {
                return $query->whereIn('id', $filteredVariable);
            });

            $variables = $variableQuery->get();
        }

        // set interview
        if (is_int($filteredInterview) || $this->isIntegerList($filteredInterview)) {
            $this->setInterview($filteredInterview);
        }

        // query
        $builder = app($this->closedAnswerModel)->query()->with('variable');

        $builder->when(isset($this->interviewId), function (Builder $query) {
            return $query->whereIn('interview_number', $this->interviewId);
        });

        return $builder
            ->whereBelongsTo($variables)
            ->get()
            ->map(function ($item) {

                $item['variable_id']   = $item->variable->id;
                $item['variable_name'] = $item->variable->name;
                $item['variable_type'] = $item->variable->type;

                $result = Arr::get($item->result, $item->variable->slug, null);

                switch ($item->variable->type) {
                    case VariableTypeEnum::OPEN:
                    case VariableTypeEnum::ALPHA:
                        $answer = Str::squish($result);
                        break;
                    case VariableTypeEnum::DATETIME:
                        $answer = Carbon::parse($result, 'Asia/Singapore');
                        break;
                    default:
                        $answer = $result;
                }

                $item['answer'] = $answer;

                return $item->only(['interview_number', 'variable_id', 'variable_name', 'variable_type', 'answer']);
            })
            ->groupBy('interview_number');

    }

    protected function isIntegerList($array): bool
    {
        if (!is_array($array) || !Arr::isList($array)) {
            return false;
        }

        return count(array_filter($array, 'is_int')) === count($array);
    }


}
