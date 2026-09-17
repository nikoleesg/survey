# nikoleesg/survey

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nikoleesg/survey.svg?style=flat-square)](https://packagist.org/packages/nikoleesg/survey)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nikoleesg/survey/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nikoleesg/survey/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nikoleesg/survey/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nikoleesg/survey/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nikoleesg/survey.svg?style=flat-square)](https://packagist.org/packages/nikoleesg/survey)

Laravel package for importing fixed-width survey exports and reading persisted
closed answers and paradata. Data is scoped by survey id and persisted in
configurable Eloquent models.

## Installation

You can install the package via composer:

```bash
composer require nikoleesg/survey
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="survey-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="survey-config"
```

This is the contents of the published config file:

```php
return [
    'survey_id' => env('SURVEY_ID', 'default'),
    'sample_model' => \Nikoleesg\Survey\Models\Sample::class,
    'variable_model' => \Nikoleesg\Survey\Models\Variable::class,
    'closed_answer_model' => \Nikoleesg\Survey\Models\Answer::class,
    'paradata_model' => \Nikoleesg\Survey\Models\Paradata::class,
    'persist_chunk_size' => 500,
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="survey-views"
```

## Usage

```php
use Nikoleesg\Survey\Facades\Answer;
use Nikoleesg\Survey\Facades\Data;

Data::setSurvey('household-2026')
    ->getClosedAnswersFromFile(storage_path('imports/closed-answers.txt'))
    ->persist();

$answers = Answer::setSurvey('household-2026')->getAnswers();
```

## Exceptions

Every exception the package throws extends `Nikoleesg\Survey\Exceptions\SurveyException` (a `RuntimeException`), so a single `catch (SurveyException $e)` covers them all.

| Exception | Thrown when |
|---|---|
| `MissingSurveyIdException` | No survey id could be resolved: none passed, none set via `setSurvey()`, and `survey.survey_id` is null or empty. |
| `UnreadableFileException` | A `get*FromFile()` loader is given a path that does not exist or cannot be read. The message names the loader and the path. |
| `NoDataLoadedException` | `persist()` or `getData()` is called before any `get*FromFile()` loader. |
| `UnparseableColumnException` | A non-blank `DATETIME`/`DATE`/`TIME` column in the closed-answer file does not match its `survey.formats.*` pattern. The message names the interview, variable, raw value and format. Blank columns are stored as `null`, not thrown. |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see the repository contribution guidelines for details.

## Security Vulnerabilities

Please report security vulnerabilities privately through GitHub's Security tab.

## Credits

- [Niko Lee](https://github.com/nikoleesg)
- [All Contributors](https://github.com/nikoleesg/survey/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
