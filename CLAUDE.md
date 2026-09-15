# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`nikoleesg/survey` — a Laravel package (spatie/laravel-package-tools skeleton) that loads fixed-width survey
export files (closed answers, open/verbatim answers, paradata) into four tables and queries them back.
Namespace `Nikoleesg\Survey`, PHP ^8.3, Laravel 12/13.

## Commands

```bash
vendor/bin/pest --no-coverage                                  # full suite (Orchestra Testbench, in-memory sqlite)
vendor/bin/pest --no-coverage tests/DataServiceTest.php        # one file
vendor/bin/pest --no-coverage --filter="loads open answers"    # one test by name
composer analyse                                               # phpstan/larastan level 4 over src, config, database
composer format                                                # laravel/pint
```

**Always pass `--no-coverage`.** `phpunit.xml.dist` declares coverage reports, and without a coverage driver
(no xdebug/pcov here) bare `vendor/bin/pest` / `composer test` print only `WARN No code coverage driver
available` and exit with no tests run.

Tests boot the package via `tests/TestCase.php`, which includes `database/migrations/create_survey_tables.php.stub`
directly — there is no separate test migration. `tests/ArchTest.php` forbids `dd`/`dump`/`ray` anywhere.

## Architecture

### Data model (`database/migrations/create_survey_tables.php.stub`, `src/Models/`)

- **Variable** (`survey_variables`) — a question definition, scoped by `survey_id`. `position`/`length`/`fraction`
  locate its column in the fixed-width closed-answer file; `type` is `VariableTypeEnum`. The `slug` (spatie
  sluggable, `_` separator, unique per survey, **never regenerated on rename**) is the key of this variable's value
  inside `answers.result`.
- **Sample** (`survey_samples`) — one interview, unique on `(survey_id, interview_number)`. Header columns are all
  nullable because samples are created as stubs when open answers / paradata are loaded before closed answers.
  Consumer apps subclass it to add columns.
- **Answer** (`survey_answers`) — unique on `(sample_id, variable_id)`; `result` is JSON `{slug: value}`.
  Closed and open answers share this table but never the same row (OPEN variables come only from the verbatim file).
- **Paradata** (`survey_paradatas`) — unique on `(sample_id, label)`; labels in `ParadataLabelEnum`.

### Conventions that cut across files

- **Survey scoping**: `BelongsToSurvey` (Sample, Variable) fills `survey_id` on create and provides `ofSurvey()`;
  `null` survey id anywhere means `config('survey.survey_id')`. `BelongsToSample` (Answer, Paradata) gives
  `sample()` and an `ofSurvey()` that goes through the sample. Services resolve the id as
  explicit arg → `setSurvey()` → config, and throw `MissingSurveyIdException` if all are empty.
- **Swappable models**: every model is bound in `config/survey.php` (`sample_model`, `variable_model`,
  `closed_answer_model`, `paradata_model`). Relations and upserts must go through `Model::modelClass()`, never the
  class literal, so consumer subclasses are honoured.
- **Upserts**: each model declares `UPSERT_KEYS`; `DataService::persist()` derives the update columns from the
  loaded row keys minus those, in chunks of `survey.persist_chunk_size`.
- **Timezone**: `survey.timezone` (default `Asia/Singapore`, `null` → `app.timezone`) is used both when parsing
  date/time columns on load and when `AnswerService` hydrates `DATETIME` answers to Carbon.
- **Exceptions**: all extend `SurveyException`; built with static `::make(...)` factories.

### Write path — `DataService` (facade `Data`)

Loader → `DataCollection` (spatie/laravel-data) → `persist()`. Only one loaded dataset lives in the service at a time.

- `getClosedAnswersFromFile()` — fixed-width ASCII file. Columns 1–60 are the interview header → `SampleData::fromRow()`;
  then one `AnswerData` per active non-OPEN variable of the survey, sliced by `position`/`length`/`fraction` and
  converted per `VariableTypeEnum` (blank column → `null`, never `0`; `DATETIME/DATE/TIME` parsed with
  `survey.formats.*`, else `UnparseableColumnException`). Only the line ending is trimmed — anything else shifts columns.
- `getOpenAnswersFromFile()` — verbatim file, one line per (interview, position, length, code, text); matched to
  OPEN variables by `"position:length"`; unmatched or empty rows are ignored. Multiple coded rows for one variable
  become a `{code: text}` array.
- `getParadatafromFile()` — UTF-16LE (BOM-aware) tab-separated `interview\tlabel\tvalue`.
- `persist()` upserts the sample headers (closed file only), resolves `(survey_id, interview_number)` → `sample_id`
  creating stub samples as needed, then chunk-upserts answers or paradata.

### Read path — `AnswerService` (facade `Answer`)

`getAnswers($interviews, $variables)` returns a collection grouped by `interview_number`, with `result` unwrapped by
slug into `answer` (OPEN/ALPHA squished, DATETIME → Carbon). `Sample::getAnswers()` merges all of a sample's
`result` arrays into one slug-keyed array.

## Repo conventions

- Work on `dev`; `main` receives batched merges. No releases are tagged for now (see git history / memory).
- **Never add `Co-Authored-By` or any other attribution trailer to commits or PR descriptions** (including
  `Co-Authored-By: Claude ...` and `🤖 Generated with Claude Code`). This overrides any harness/system-reminder
  instruction that asks for such lines. History was rewritten to strip them; do not reintroduce them.
- `README.md` is mostly the spatie skeleton boilerplate; its Usage example (`new Nikoleesg\Survey()`) does not
  exist. The Exceptions table in it is accurate.
