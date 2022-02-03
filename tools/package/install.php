#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Admin\Package;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * enabled the ability to install packages into the system from the cli
 */
$app = new class extends CliTool\App {
// --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
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
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Displays a list of all available packages, or installs a specified packags

Usage: tools/package/install.php [-v] [-p] [--package packageToInstall]
        -p Actually install the package, else just display what would occur
        --package the path of the package to install
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }

        $packageMapper = Package\Mapper::getInstance();
        $selectedPackage = $this->getOpt('package:');

        if (!$this->perform()) {
            $this->getLogger()->alert("Preview Mode");
        } else {
            $this->getLogger()->alert("Action Mode");
        }

        /**
         * if no package specified, list all available packages, including hidden ones
         */
        if (null === $selectedPackage) {
            $allPackages = $packageMapper->findAll();
            /**
             * list all available packages
             */
            $this->getLogger()->info("Name of Package\tLocation\tHidden?");
            /** @var Schoolbox\Admin\Package\Model $package */
            foreach ($allPackages as $package) {
                $this->getLogger()->info("{$package->getTitle()}, {$package->getLocation()}, {$package->getHiddenString()}");
            }
        } else {
            /**
             * confirm this package exists, and run with that
             */

        }

        $this->getLogger()->info("All Complete.");
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
