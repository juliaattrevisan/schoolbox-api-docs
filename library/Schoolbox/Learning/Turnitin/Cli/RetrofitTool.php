<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Exception\Exception;
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
     * Retrofit *all* previous student submissions
     */
    private const OPT_LONG_RETROFIT_ALL = 'all';


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
            self::OPT_LONG_RETROFIT_ALL
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

The --all flag lets you retrofit *all* previous students' submissions regardless of
the submitted date. This lets you accommodate certain schools who use a single Due Work
to represent a multi-part assessment which is unconventional for our intended usage.

Otherwise, if you omit --all, it'll just retrofit each student's most recent submission.
(This is the default behaviour)

Usage: retrofit.php --assessment=1234 [--instance=INSTANCE_ID] [--all]


HELP;
            exit;
        }

        /* Check if turnitin is configured sufficiently
         */
        if (!(new Helper($this->getConfig()))->isConfigured()) {
            $this->getLogger()->info('Turnitin configuration invalid.');
            exit(1);
        }

        /* Check that your api setup is functioning
         * We need this api working
         */
        $api = new Turnitin\Api(
            $this->getConfig()->get('site_version'),
            $this->getConfig()->get('turnitin_api_host'),
            $this->getConfig()->get('turnitin_api_key')
        );

        /* If the basic test() failed, then your turnitin config is broken
         * and your api host/key combo is somehow wrong
         */
        try {
            $api->test();
        } catch (Exception $e) {
            $this->getLogger()->warning($e->getMessage());
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

        /* Tell the user the manner of their retrofitting
         */
        $retrofitAll = (bool)$this->getOpt(self::OPT_LONG_RETROFIT_ALL);

        if ($retrofitAll) {
            $this->getLogger()->info(sprintf(
                'Retrofitting ALL submissions (and their files) for each student in assessment %s',
                $this->getOpt(self::OPT_LONG_ASSESSMENT_ID)
            ));
        } else {
            $this->getLogger()->info(sprintf(
                'Retrofitting the most recent submission (and their files) for each student in assessment %s',
                $this->getOpt(self::OPT_LONG_ASSESSMENT_ID)
            ));
        }

        (new Turnitin\Retrofit(Turnitin\Group\Mapper::getInstance(), Turnitin\File\Mapper::getInstance(), SubmissionBox\Submission\SingleFile\Mapper::getInstance(), SubmissionBoxMapper::getInstance(), $this->getLogger()))
            ->retrofit((int)$this->getOpt(self::OPT_LONG_ASSESSMENT_ID), $retrofitAll);

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
