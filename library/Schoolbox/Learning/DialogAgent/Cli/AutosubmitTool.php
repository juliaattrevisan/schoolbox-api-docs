<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent\Cli;

use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Url\Factory;

/**
 * Dialog Agent Autosubmit
 *
 * Automatically submits any unsubmitted responses made by students when the activity's Due/Close Date
 * is reached. It will submit on behalf of the student.
 */
class AutosubmitTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


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

Dialog Agents (Discussion Activities) allow students to interact with an AI to discuss topics. Students can start a
discussion but neglect to officially submit their response to the activity. This tool will set the submitted_at value
to the end datetime of the activity on behalf of the student when the activity reaches its due/close date (these dates
are synonymous specifically in Dialog Agents). The student will be presumed to be the person who submitted it since we
don't have a "system user" to do that.

 
Usage:    autosubmit.php --perform [-vvv]

HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Dialog Agent Autosubmit');
        $this->getLogger()->info('');
    }
}
