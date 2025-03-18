<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Csv;

use Schoolbox\Application\CliTool;

class Cli extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * --file
     *
     * Load csv contents from a file path specified by this arg
     */
    private const OPT_LONG_FILE = 'file:';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE,
        ];
    }

    /** @inheritdoc */
    public function runAsRoot(): ?bool
    {
        return false;
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Checks a csv for any possible issues, BOM, UTF8 in the header, or if there is UTF8 in the rest of the file
Usage: tools/csvCheck.php [-v] --file
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)
        --file CSV input file

HELP;
            exit;
        }
        $filename = $this->getOpt(self::OPT_LONG_FILE);
        if (null === $filename) {
            $this->getLogger()->error("no --file specified");
            exit();
        }
        //load file
        $fp = fopen($filename, 'r');
        try {
            $valid = new ValidCSV($fp);
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            exit();
        }
        //check if the file has a BOM, which we should always be removing, but good to know.
        if ($valid->hasBOM()) {
            $this->getLogger()->warning("File has BOM character");
        } else {
            $this->getLogger()->info("File does not contain a BOM character");
        }

        //check the header row (excluding BOM character), this is where imports usually fail.
        if ($valid->hasAsciiHeaderRow()) {
            $this->getLogger()->info("Headers are clean of UTF8");
        } else {
            $this->getLogger()->warning("File has non ASCII characters in the header row");
        }

        //dump all locations where UTF8 characters exist, including the BOM and headers.
        $valid->locateUTF8($this->getLogger());

        $this->deprecationNotice("SA-19334");
    }
}
