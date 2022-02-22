#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * checks csv for possible character issues
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * --file
     *
     * Load xml contents from a file path specified by this arg
     */
    private const OPT_LONG_FILE = 'file:';

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE
        ];
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** Hands off execution over to the App */
    public function run(): void
    {
        $filename = $this->getOpt(self::OPT_LONG_FILE);
        if (null === $filename) {
            $this->getLogger()->error("no --file specified");
            exit();
        }
        //load file
        $fp = fopen($filename, 'r');

        if (!is_resource($fp)) {
            $this->getLogger()->error(sprintf('File "%s" not found, or is not readable!', $filename));
            exit();
        }

        $metadata = stream_get_meta_data($fp);
        $seekable = $metadata['seekable'] ?? false;
        if (!$seekable) {
            $this->getLogger()->error('File stream does not support seeking');
            exit();
        }

        //check for bom
        $bom = "\xef\xbb\xbf";
        if (fgets($fp, 4) === $bom) {
            $this->getLogger()->notice('file contains BOM (\xef\xbb\xbf) in the first line SA-13246 fixes this for the user import.');
        }
        if (fgets($fp, 4) !== $bom) {
            // BOM not found - rewind pointer to start of file.
            rewind($fp);
        }

        //parse csv
        $lineCount = 0;
        while(!feof($fp) && ($line = fgetcsv($fp)) !== false) {
            $lineCount++;
            $cellCount = 0;
            foreach ($line as $cell) {
                $cellCount++;
                //check line for non ascii characters
            if (false === mb_detect_encoding($cell, 'ASCII', true)) {
                    $this->getLogger()->notice(sprintf('Line "%d", Cell "%d" contains non ascii character(s)', $lineCount, $cellCount));
                    //build a nice hex code, separating each hex code by a space
                    $hexCode = join(' ', str_split(bin2hex($cell), 2));
                    $this->getLogger()->notice(sprintf('Cell: "%s" Hex: "%s"', $cell, $hexCode));
                }
            }

        }

    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
