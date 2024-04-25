<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Codec\PayloadFilename;
use Schoolbox\Resource\Storage\FilesystemProxy;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --hash
     *
     * The input liss payload that you want to convert
     */
    private const OPT_LONG_HASH = 'hash:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HASH,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Converts a serialized liss payload into a json-formatted version,
saving it into a text file for you to inspect at your discretion.
Use the redirect symbol to do it, otherwise it prints to terminal.

Don't forget to clean up after yourself when you're done

Usage: deserialize.php --hash=... > ./path/to/output.txt

Example: deserialize.php --hash=abcd1234jkl5678 > output.txt


HELP;
            return;
        }

        /* See if the file exists and is in a readable condition
         */
        $inputHash = trim((string)$this->getOpt(self::OPT_LONG_HASH));

        /* This is needed to process liss data from storage files
         */
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

        $storageMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

        try {
            $file = $storageMapper->findByHash($inputHash);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->getLogger()->warning(sprintf(
                'LISS Payload for %s not found; exiting.',
                $inputHash
            ));

            exit(1);
        }

        /* Once we can get the saved payload, try to reconstruct it
         * into something we can feed back into the Receiver
         */
        $mockMessage = unserialize($filesystemProxy->read($file), [
            'allowed_classes' => true
        ]);

        /* You could also jsonize it, replacing everything with a stdClass representation
         *  because we don't care about phpxmlrpc class hierarchy anymore, though ultimately
         * you'll need to comprehend the object's internal structure in either case.
         *
         * print_r(json_decode(json_encode(...
         */
        print_r($mockMessage);
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
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
