<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Clipboard\Hierarchy\Factory;

/**
 * Encapsulated cli tool
 */
class HierarchyTool extends CliTool\App
{

    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import Clipboard Category/Subcategory/Teams into schoolbox Divisions/Teams equivalent.

Usage: hierarchy.php


HELP;
            exit;
        }

        $this->getLogger()->info('Clipboard to Schoolbox Fixtures Hierarchy synchronisation.');

        try {
            $composites = (new Factory(
                $this->getLogger(),
                $this->perform(),
                (string)$this->getConfig()->get('clipboard_endpoint_host'),
                (string)$this->getConfig()->get('clipboard_endpoint_token'),
            ))->produce();
        } catch (\DomainException $e) {
            $this->getLogger()->warning($e->getMessage());

            return;
        }

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
