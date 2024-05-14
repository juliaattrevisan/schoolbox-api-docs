<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;

/**
 * Detect legacy quizzes containing cloze exercises.
 * These are known to have imported incorrectly at the start of 2024.
 */
class DetectClozeTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * @var Api
     */
    private Api $api;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find out how many legacy quizzes contain cloze exercise questions (fillgap)
because we mis-imported them at the start of 2024 during the mega migration.
These will have used the wrong regex to parse answers.

Usage: detectcloze.php [-vvv]


HELP;
            exit;
        }
    }
}
