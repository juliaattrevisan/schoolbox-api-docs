<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Clipboard\Hierarchy\Factory;

class HierarchyTool extends CliTool\App
{

    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --flag
     *
     * An example arg that's a boolean on/off
     */
    private const OPT_LONG_FLAG = 'flag';


    /**
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FLAG,
            self::OPT_LONG_INPUT
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Write an explanation about what your tool does and what arguments it accepts

Usage: example.php [--flag] [--input mytest.txt]
        --flag      Example flag (on/off)
        --input     Example input value (arg which has user-inputted value)


HELP;
            exit;
        }

        /* FIXME Main algorithm goes here
         */
        $this->getLogger()->info('Script commencing.');

        $composites = (new Factory(
            $this->getLogger(),
            $this->perform(),
            $this->getConfig()->get('clipboard_endpoint_host'),
            $this->getConfig()->get('clipboard_endpoint_token'),
        ))->produce();

        foreach ($composites as $composite) {
            $composite->import();
        }
        
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
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
