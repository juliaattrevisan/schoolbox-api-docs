#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Api;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Connector\Isams\Base\Endpoint\MockApi;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Specification;
use Zend\Uri\Exception\InvalidUriPartException;

require_once __DIR__ . '/../../../conf/core/require.php';

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
     * --file
     *
     * Path to input xml file
     */
    private const OPT_LONG_FILE = 'file:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

ISAMS XML Specification validator

Usage: specification.php [--file]
        --file      Optional path to a locally-cached xml file e.g. ../../../www/test.xml
                    Absence of this arg will cause the tool to access the api endpoint instead


HELP;
            exit;
        }

        /* Spawn api based on args
         *
         * This gives you the cli choice of loading from an api endpoint
         * or a locally-cached xml file instead
         */
        $file = $this->getOpt(self::OPT_LONG_FILE);

        if ($file) {
            $this->getLogger()->info("Loading from locally-cached xml file: {$file}");
            $api = new MockApi($file);
        } else {
            $this->getLogger()->info('Accessing api endpoint');
            $api = new Api($this->getConfig()->get('external_ups_provider_batchxml_host'), $this->getConfig()->get('external_ups_provider_batchxml_key'));
        }

        $composite = new Specification(
            $this->getLogger(),
            new Batchxml($api)
        );

        try {
            $composite->check();
        } catch (\RuntimeException $e) {
            $this->getLogger()->critical("Cannot access api endpoint: {$e->getMessage()}");
        } catch (InvalidUriPartException $e) {
            $this->getLogger()->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
