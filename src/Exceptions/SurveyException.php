<?php

namespace Nikoleesg\Survey\Exceptions;

use RuntimeException;

/**
 * Base class for every exception the package throws; catch this to handle
 * them all at once.
 */
abstract class SurveyException extends RuntimeException
{
}
