<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Helper;

/**
 * CLI Tool for historically adding files that should be in Turnitin, but aren't
 */
class HistoricalTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const TURNITIN_LOCK_NAME = 'turnitin_historical';


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
     * --start=...
     *
     * Student submission must be created after this date
     * If omitted, defaults to NOW() - 1 year
     */
    private const OPT_LONG_START = 'start:';


    /**
     * --end=...
     *
     * Student submission must be created before this date
     * If omitted, defaults to NOW()
     */
    private const OPT_LONG_END = 'end:';


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
            self::OPT_LONG_START,
            self::OPT_LONG_END,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Adds older documents from a distant past into turnitin_report so that
turnitin can use those documents' contents in future similarity checks,
but without having to go and manufacture billions of reports. Old files'
similarity reports are serviced by plagscan documents.

If a start date is provided, student submissions will be added as historical
documents only if they were submitted after the start date. If omitted, it
defaults to a year before the current date.

If an end date is provided, student submissions will be added as historical
documents only if they were submitted before the end date. If omitted, it
defaults to the current date.

Historical documents get added to turnitin_report in the same way as every
other turnitin document, except the historical flag will be 1 instead of the
default 0. This allows us to treat them a bit specially in their processing.


Usage: historical.php [--start=2022-02-02] [--end=2022-03-03] [--instance=INSTANCE_ID]


HELP;
            exit;
        }

        /* Check if turnitin is configured sufficiently
         */
        if (!(new Helper($this->getConfig()))->isConfigured()) {
            $this->getLogger()->info('Turnitin configuration invalid.');
            exit;
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
        } catch (Turnitin\Exception\Credentials $e) {
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

        /* Interpret the date range from cli options
         */
        if ($this->getOpt(self::OPT_LONG_START)) {
            $start = \Alaress_Date_Format::newFromTimestamp(strtotime($this->getOpt(self::OPT_LONG_START)))->startOfDay();
        } else {
            $start = \Alaress_Date_Format::now()->addYears(-1)->startOfDay();
        }

        if ($this->getOpt(self::OPT_LONG_END)) {
            $end = \Alaress_Date_Format::newFromTimestamp(strtotime($this->getOpt(self::OPT_LONG_END)))->endOfDay();
        } else {
            $end = \Alaress_Date_Format::now()->endOfDay();
        }

        $this->getLogger()->debug(sprintf(
            'Historically adding stuff between %s and %s',
            $start->getDatabase(),
            $end->getDatabase()
        ));

        (new Turnitin\Historical(
            Turnitin\File\Mapper::getInstance(),
            \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance(),
            \Schoolbox\Resource\Storage\Mapper::getInstance(),
            $this->perform(),
            $this->getLogger()
        ))->historical($start, $end, $lock);

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        } else {
            $this->getLogger()->info('Done.');
        }

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
