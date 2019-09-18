#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Isams\Base\Api;
use Schoolbox\External\Connector\Isams\Base\Importer\Profile;
use Schoolbox\External\Connector\Isams\Base\MockApi;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Connector\Isams\Base\Importer\Batchxml;
use Zend\Uri\Exception\InvalidUriPartException;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * ISAMS Profile Importer
 *
 * Imports from an xml file and populates the following tables:
 *   sis_user_profile
 *   sis_user_profile_staff
 *   sis_user_profile_student
 *   sis_user_profile_parent
 *   sis_user_profile_relation
 */
$app = new class extends CliTool\App
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


    /**
     * Execute each strat in the composite
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import user profile data from ISAMS xml into local schema. This is used by UPS to create user accounts.

Usage: profileImporter.php --perform


HELP;
            exit;
        }

        /* Spawn api
         * TODO: Swap with actual live connection when ready
         */
        $api = new Api($this->getConfig()->get('external_ups_provider_batchxml_host'), $this->getConfig()->get('external_ups_provider_batchxml_key'));

        $composite = new Profile\Composite(
            $this->getLogger(),
            $this->perform(),
            new Batchxml($api),
            UserProfile\Mapper::getInstance(),
            UserProfile\Student\Mapper::getInstance(),
            UserProfile\Parent\Mapper::getInstance(),
            UserProfile\Staff\Mapper::getInstance(),
            UserProfile\Relation\Mapper::getInstance()
        );

        try {
            $composite->import();
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
