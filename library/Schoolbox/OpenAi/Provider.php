<?php
/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

/**
 * Enum for the available OpenAI providers.
 *
 * Each option corresponds to an implemented OpenAI-compatible AI provider.
 */
enum Provider: string
{
    case OpenAi = 'OpenAI';
    case Azure  = 'Azure';
}
