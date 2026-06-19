<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Format;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Session\Log\Model;

/**
 * Interface for log format translation strategies
 * Each enum value from LogFormat should have an implementation of this interface
 */
interface IStrategy
{
    /**
     * Returns this implementation's LogFormat
     * @return LogFormat
     */
    public static function getLogFormat(): LogFormat;

    /**
     * Translate a saved log's inputText back into structured data for display
     *
     * @param Model $log
     * @return array an array ready to be used in an API response
     */
    public function translateInput(Model $log): array;

    /**
     * Translate a saved log's outputText back into structured data for display
     *
     * @param Model $log
     * @return array an array ready to be used in an API response
     */
    public function translateOutput(Model $log): array;
}
