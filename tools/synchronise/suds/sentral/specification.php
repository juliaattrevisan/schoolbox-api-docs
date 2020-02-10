#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Specification;
use Schoolbox\External\Importer\Endpoint\Specification\CliRenderer;

require_once __DIR__ . '/../../../../conf/core/require.php';

/**
 * Validate xml
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
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Sentral Endpoint Specification validator

Checks that you can get to each of the endpoints we utilise when importing data into SUDS.

Usage: specification.php

HELP;
            exit;
        }

        /* Spawn api based on args
         */
        $this->getLogger()->info('Accessing remote api endpoint');
        $api = new Api($this->getConfig()->get('schooldata_import_endpoint_host'), $this->getConfig()->get('schooldata_import_endpoint_key'));

        $specification = (new Specification($api));
        $renderer = new CliRenderer($this->getLogger());

        $renderer->render($specification->check());
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
