#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Aura\Di;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require-cli.php';

// TODO - START - Delete this once SA-8248 is merged. It will be handled by the require above.
require_once __DIR__ . '/../../../www/conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/constants.php';
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once SITE_root . '/library/Autoloader.php';
Autoloader::initialise();
// TODO - END

// This tools CLI options
const OPT_SHORT_HELP       = 'h';            // print help
const OPT_LONG_HELP        = 'help';         // print help
const OPT_LONG_HASH        = 'hash:';        // session hash
const OPT_LONG_USERNAME    = 'username:';    // username
const OPT_LONG_STUDENT_ID  = 'student-id:';  // student-id
const OPT_LONG_YEAR_LEVEL  = 'year-level:';  // year-level
const OPT_LONG_CURRICULUM  = 'curriculum:';  // curriculum

$shortOpts = [OPT_SHORT_HELP];
$longOpts  = [OPT_LONG_HASH, OPT_LONG_USERNAME, OPT_LONG_STUDENT_ID, OPT_LONG_YEAR_LEVEL, OPT_LONG_CURRICULUM,];
$kernel    = new Schoolbox\Application\CliTool\Kernel($shortOpts, $longOpts);

$app = new class() extends CliTool\App{

    private const CURRICULUM_IB  = 'ib';
    private const CURRICULUM_HSC = 'hsc';

    /** @var resource */
    private $outStream;

    /** @var LoggerInterface */
    private $logger;

    /** @var string|null */
    private $sessionHash;

    /** @var string|null */
    private $username;

    /** @var string|null */
    private $studentId;

    /** @var string|null */
    private $yearLevel;

    /** @var string|null */
    private $curriculum;


    /**
     * @param Di\Container    $di
     * @param resource        $outStream
     * @param LoggerInterface $logger
     * @param string[]        $appOptions
     */
    public function run(Di\Container $di, $outStream, LoggerInterface $logger, array $appOptions): void
    {
        $this->outStream = $outStream;
        $this->logger    = $logger;

        // Did user request help?
        if (\array_key_exists(OPT_SHORT_HELP, $appOptions) || \array_key_exists(OPT_LONG_HELP, $appOptions)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Parse command-line options. If invalid options detected, then print help and exit.
        if (!$this->parseAppOptions($appOptions)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->execute();
    }


    /**
     * Parses options and returns whether all options are valid
     *
     * @param array $appOptions
     * @return bool
     */
    private function parseAppOptions(array $appOptions): bool
    {
        $valid             = true;
        $this->sessionHash = $this->getOpt(OPT_LONG_HASH, $appOptions);
        $this->username    = $this->getOpt(OPT_LONG_USERNAME, $appOptions);
        $this->studentId   = $this->getOpt(OPT_LONG_STUDENT_ID, $appOptions);
        $this->yearLevel   = $this->getOpt(OPT_LONG_YEAR_LEVEL, $appOptions);
        $this->curriculum  = $this->getOpt(OPT_LONG_CURRICULUM, $appOptions);
        if ($this->curriculum && !\in_array($this->curriculum, [self::CURRICULUM_IB, self::CURRICULUM_HSC], true)) {
            $this->logger->alert("Invalid curriculum: '$this->curriculum'");
            $valid = false;
        }
        return $valid;
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
$help = "
CLI wrapper to generate a PDF Archive of Participation & Subject Reports

By default, writes archive to STDOUT (1>) and writes log to STDERR (2>)

This can be changed by providing an output and/or log file. If provided, tool will not write to STDOUT or STDERR, respectively.

 Examples:
 * Write log to terminal, output to archive.zip
      ./export.php 1> archive.zip
      ./export.php -o archive.zip
 * Append log to export.log, output to archive.zip
      ./export.php 2>> export.log 1> archive.zip
      ./export.php -l export.log -o archive.zip\n";

        \fwrite(STDOUT, $help);
    }


    /**
     * Start doing the work.
     */
    private function execute(): void
    {
        $this->logger->emergency('emergency');
        $this->logger->alert('alert');
        $this->logger->critical('critical');
        $this->logger->error('error');
        $this->logger->warning('warning');
        $this->logger->notice('notice');
        $this->logger->info('info');
        $this->logger->debug('debug');
    }
};


$kernel->runApp($app);
