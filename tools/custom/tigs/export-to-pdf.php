#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Alaress\Lumberjack;
use Aura\Di;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;



/*
 * CLI wrapper to generate a PDF Archive of Participation & Subject Reports
 *
 * By default, writes archive to STDOUT (1>) and writes log to STDERR (2>)
 *
 * This can be changed by providing an output and/or log file. If provided, tool will not write to STDOUT or STDERR, respectively.
 *
 * Examples:
 * * Write log to terminal, output to archive.zip
 *      ./export.php 1> archive.zip
 *      ./export.php -o archive.zip
 * * Append log to export.log, output to archive.zip
 *      ./export.php 2>> export.log 1> archive.zip
 *      ./export.php -l export.log -o archive.zip
 */

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "Tool must be run in CLI");
    exit(1);
}

$shortOpts = 'h:u:s:y:c:o:l:'; // hash, username, student's user.id, yearLevel, curriculum, output file, log file
$opts      = require __DIR__ . '/../../../conf/core/require-cli.php';
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'TIGS PDF Snapshots',
    [
//        'verbose'     => $opts['v'] ?? null,
//        'verbose'     => [0,0,0],
        'verbose'     => false,
        'errors'      => E_ALL,
//        'dryRun'      => isset($opts['dry-run']),
//        'retryFailed' => isset($opts['retry-failed']),
//        'batchSize'   => isset($opts['batch-size']) ? (int)$opts['batch-size'] : DEFAULT_BATCH_SIZE,
//        'maxAttempts' => isset($opts['max-attempts']) ? (int)$opts['max-attempts'] : DEFAULT_MAX_ATTEMPTS,
//        'maxSkips'    => isset($opts['max-skips']) ? (int)$opts['max-skips'] : DEFAULT_MAX_SKIPS,
//        'baseBackOff' => isset($opts['base-back-off']) ? (int)$opts['base-back-off'] : DEFAULT_BASE_JITTER_BACK_OFF,
//        'timeout'     => isset($opts['timeout']) ? (int)$opts['timeout'] : DEFAULT_TIMEOUT,
//        'failureRate' => isset($opts['failure-rate']) ? (float)$opts['failure-rate'] : 0.0,
    ]
);

$logger = Lumberjack\Logger::createErrorLogger(STDERR, Monolog\Logger::DEBUG, $fields);
$logger = new Lumberjack\Logger(null, STDERR, Monolog\Logger::DEBUG, $fields, \in_array('porcelain', $fields, true), Monolog\Logger::DEBUG);

// Add Sentry handler into logging system if possible
if ($kernel->getDependencyInjector()->has('sentry')) {
    /** @var Raven_Client $ravenClient */
    $ravenClient  = $this->getDependencyInjector()->get('sentry');
    $ravenHandler = new RavenHandler($ravenClient, Monolog\Logger::CRITICAL);
    /** @var LineFormatter $formatter */
    $formatter = $ravenHandler->getFormatter();
    $formatter->includeStacktraces(true);
    $logger->pushHandler($ravenHandler);
}

$kernel->setLogger()

$exporter = new class extends App{

//    private $in;

    private const CURRICULUM_IB  = 'ib';
    private const CURRICULUM_HSC = 'hsc';

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    /** @var string */
    private $userHash;

    /** @var string */
    private $username;

    private $studentId;

    private $studentYearLevel;

    private $studentCurriculum;

    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param LoggerInterface $log
     * @param array         $opts
     * @return void
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts): void
    {
        // Validate all supplied options, and print help if invalid options or help explicitly requested.
        $valid = $this->parseOptions($opts);
        if (!$valid || \array_key_exists('h', $opts)) {
            $this->printHelp();
            exit($valid ? 0 : 1);
        }

        $this->parseOptions($opts);
        $this->execute();
    }

    private function parseOptions(array $opts): bool
    {
        $invalid = false;

        // Configure logging
        $this->err = isset($opts['l']) ? \fopen($opts['l'], 'ab') : STDERR;
        if(!$this->err){
            $this->err = STDERR;
            $this->log("Unable to log to '{$opts['l']}'");
            $invalid = true;
        }

        // Configure outfile
        $this->out = isset($opts['o']) ? \fopen($opts['o'], 'wb') : STDOUT;
        if(!$this->out){
            $this->out = STDOUT;
            $this->log("Unable to set output to '{$opts['o']}'");
            $invalid = true;
        }

        $this->username = $opts['u'] ?? null;
        $this->studentId = $opts['s'] ?? null;
        $this->studentYearLevel = $opts['y'] ?? null;

        if(isset($opts['c'])){
            if(\in_array($opts['c'], [self::CURRICULUM_IB, self::CURRICULUM_HSC], true)){
                $this->studentCurriculum = $opts['c'];
            } else {
                fwrite($this->err, "Unable to set output to '{$opts['o']}'");
            }

        }



        // 'h:u:s:y:c:o:l:'; // hash, username, student's user.id, yearLevel, curriculum, output file, log file
        return $invalid;
    }

    private function log(string $message): void
    {
        \fwrite($this->err ?? STDERR, $message);
    }

    private function printHelp(): void
    {
        fwrite($this->out, "HELP");
    }

    private function execute(): void
    {

    }
};

try{
    $kernel->run($exporter);
} catch (\Throwable $e){
    echo $e->getTraceAsString();
    exit(1);
}








// TODO validate and cast all options

$outFile = ($opts['o'] ?? false) ? fopen($opts['o'], 'wb'): STDOUT;
$errFile = ($opts['l'] ?? false) ? fopen($opts['l'], 'ab'): STDOUT;


fwrite($errFile, "Logging to STDERR\n");
fwrite($outFile, "Writing to STDOUT\n");


//var_dump($options);
die();


$options = [
    'reverse' => [
        'short' => 'r',
        'long' => 'reverse',
    ],
    'table-name' => [             // Invalid due to ambiguity
        'short' => 't:',
        'long'  => 'table:',
    ],
];

$fields  = [
    'help',
    'perform',
    'retry-failed',
    'batch-size:',
    'max-attempts:',
    'max-skips:',
    'base-back-off:',
    'timeout:',
    'failure-rate:',
];
$options = require __DIR__ . '/../../../conf/core/require-cli.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Kami Uploader',
    [
        'verbose'     => $options['v'] ?? null,
        'errors'      => E_ALL,
        'dryRun'      => isset($options['dry-run']),
        'retryFailed' => isset($options['retry-failed']),
        'batchSize'   => isset($options['batch-size']) ? (int)$options['batch-size'] : DEFAULT_BATCH_SIZE,
        'maxAttempts' => isset($options['max-attempts']) ? (int)$options['max-attempts'] : DEFAULT_MAX_ATTEMPTS,
        'maxSkips'    => isset($options['max-skips']) ? (int)$options['max-skips'] : DEFAULT_MAX_SKIPS,
        'baseBackOff' => isset($options['base-back-off']) ? (int)$options['base-back-off'] : DEFAULT_BASE_JITTER_BACK_OFF,
        'timeout'     => isset($options['timeout']) ? (int)$options['timeout'] : DEFAULT_TIMEOUT,
        'failureRate' => isset($options['failure-rate']) ? (float)$options['failure-rate'] : 0.0,
    ]
);

