#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Api;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Collator;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Receiver;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;
use Schoolbox\External\Importer\Endpoint\Specification\CliRenderer;
use Zend\Uri\Exception\InvalidUriPartException;

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

Kamar XML Specification validator

Usage: specification.php


HELP;
            exit;
        }

        /* Spawn api based on args
         */
        $this->getLogger()->info('Accessing local api endpoint');

        $expectedXmls = (new Collator())->collate($this->getConfig()->get('schooldata_import_endpoint_host'));

        $specifications = [
            Receiver::XML_FULL => new Profile\Specification(new Batchxml(new Api($expectedXmls[Receiver::XML_FULL]))),
            Receiver::XML_STUDENTTIMETABLES => new Timetable\Specification(new Batchxml(new Api($expectedXmls[Receiver::XML_STUDENTTIMETABLES]))),
        ];

        $renderer = new CliRenderer($this->getLogger());

        $result = [];

        foreach ($specifications as $key => $specification) {
            try {
                $result = array_merge($result, $specification->check());
            } catch (\RuntimeException $e) {
                $result[] = [
                    'message' => "Cannot access api endpoint: cannot open/read at least one {$key} xml",
                    'status' => CliRenderer::STATUS_EMPTY
                ];
            } catch (InvalidUriPartException|InvalidArgumentException $e) {
                $result[] = [
                    'message' => "Cannot access api endpoint, bad url: {$e->getMessage()}",
                    'status' => CliRenderer::STATUS_EMPTY
                ];
            }
        }

        $renderer->render($result);
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
