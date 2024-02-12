<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Interop\Queue\Exception as InteropQueueException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;

abstract class BaseTool extends CliTool\App
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * Short options:
     * * -h: help
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * Long options:
     * * --help:  help
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * Duplicator for quiz data.
     */
    protected Api\Duplicator $duplicator;

    /**
     * Data source for assessments.
     */
    protected MapperProxy $assessmentDataSource;

    /**
     * Process the quiz, in whichever way this tool processes quizzes.
     *
     * @param QuizDomainModel $quiz
     * @return void
     */
    abstract protected function processQuiz(QuizDomainModel $quiz): void;

    /**
     * Print help text
     *
     * @return void
     */
    abstract protected function printHelp(): void;
}
