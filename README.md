# This is my package survey

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nikoleesg/survey.svg?style=flat-square)](https://packagist.org/packages/nikoleesg/survey)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nikoleesg/survey/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nikoleesg/survey/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nikoleesg/survey/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nikoleesg/survey/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nikoleesg/survey.svg?style=flat-square)](https://packagist.org/packages/nikoleesg/survey)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/survey.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/survey)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

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
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="survey-views"
```

## Usage

```php
$survey = new Nikoleesg\Survey();
echo $survey->echoPhrase('Hello, Nikoleesg!');
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

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Niko Lee](https://github.com/nikoleesg)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
