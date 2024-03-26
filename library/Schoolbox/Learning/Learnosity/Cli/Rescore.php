<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Url\Factory;

/**
 * Learnosity Attempt Rescore
 *
 * Causes all attempts of a particular quiz to be rescored by learnosity
 */
class Rescore extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Short Answer questions didn't have a Points attributed to them, due to a mistake I made
in the initial import procedure. This is rectified by ticking Enable Auto Scoring in the
learnosity user interface, but any attempts that were made and tallied *without* the
Points value will still be stuck like that: they need to be rescored/retallied via this
tool, which lets you specify a particular quiz whose attempts you want to update.

It accepts a learnosity_reference for a quiz whose attempts you want to rescore.

Notes:
* This always mutates data; it's always in Perform mode.
* The verbose mode will log more diagnostic information but you shouldn't use it in prod.

Usage: rescore.php --quiz=... [-vvv]


HELP;
            exit;
        }

        $baseUrl = (new Factory($this->getConfig()))->buildFullyQualified();

        $api = new Api(
            $baseUrl->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );
    }
}
