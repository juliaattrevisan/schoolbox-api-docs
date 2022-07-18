<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Helper;

/**
 * CLI Tool for retrofitting files that should be in Turnitin, but aren't
 */
class RetrofitTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const TURNITIN_LOCK_NAME = 'turnitin_retrofit';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --instance=...
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * --assessment=...
     *
     * The assessment whose submissions need retrofitting
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';


    /**
     * Lock ttl
     */
    private const LOCK_TTL = 10 * 60;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_ASSESSMENT_ID,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

For the assessment id provided, find the latest student_submission's files and, where
needed, put them into turnitin_report for processing. This occurs mainly when teachers
activate Similarity Check after some students have submitted, but not others, and you
needed to put those -others- into turnitin_report.

Usage: retrofit.php --assessment=1234 [--instance=INSTANCE_ID]


HELP;
            exit;
        }

        /* Check if turnitin is configured sufficiently
         */
        if (!(new Helper($this->getConfig()))->isConfigured()) {
            $this->getLogger()->info('Turnitin configuration invalid.');
            exit(1);
        }

        /* Check that this isn't currently running
         */
        try {
            $lock = $this->acquireLock(self::TURNITIN_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            exit(1);
        }

        if (!$this->getOpt(self::OPT_LONG_ASSESSMENT_ID)) {
            $this->getLogger()->info('An assessment id is required.');
            exit(1);
        }

        $this->getLogger()->info(sprintf(
            'Retrofitting student submission files for assessment %s',
            $this->getOpt(self::OPT_LONG_ASSESSMENT_ID)
        ));

        (new Turnitin\Retrofit(
            Turnitin\Group\Mapper::getInstance(),
            Turnitin\File\Mapper::getInstance(),
            SubmissionBox\Submission\SingleFile\Mapper::getInstance(),
            $this->getLogger())
        )->retrofit((int)$this->getOpt(self::OPT_LONG_ASSESSMENT_ID));

        $this->getLogger()->info('Done.');

        /* Releasing control
         */
        $lock->release();
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
