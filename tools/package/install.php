#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Admin\Package;
use Schoolbox\UserMapper;

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
        --package name of the package to install
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }

        $packageMapper = Package\Mapper::getInstance();
        $userMapper = UserMapper::getInstance();
        $selectedPackage = $this->getOpt('package');
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
            $this->getLogger()->info("Name of Package\tLocation\tHidden?\tInstalled");
            /** @var Package\Model $package */
            foreach ($allPackages as $package) {
                $this->getLogger()->info("{$package->getTitle()}, {$package->getLocation()}, {$package->getHiddenString()}, {$package->isInstalledString()}");
            }
        } else {
            /**
             * confirm this package exists, and run with that
             */
            $this->getLogger()->notice("Requested to install {$selectedPackage}");
            try {
                /** @var Package\Model $package */
                $package = $packageMapper->findOneByColumn('location', $selectedPackage);
            } catch (\Schoolbox\Mapper\Exception\NotFound $e) {
                $this->getLogger()->error("Package not found, confirm you are attempting to install the correct package");
            }
            $this->getLogger()->info("Found {$package->getTitle()}");
            /**
             * package found, is it already installed?
             */
            if (true === $package->isInstalled()) {
                $this->getLogger()->error("Package is already installed.");
            }
            /**
             * Lets attempt to install the package
             */
            try {
                /**
                 * lets just say we are alaress, and installing as such
                 */
                if ($this->perform()) {
                    $user             = $userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);
                    $packageInstaller = new Package\Installer($packageMapper);
                    $packageInstaller->installPackage($package, $user);
                    $this->getLogger()->info("Installed {$package->getTitle()}");
                } else {
                    $this->getLogger()->info("Would have installed {$package->getTitle()}");
                }
            } catch (Schoolbox\Application\Exception\Exception $e) {
                $this->getLogger()->error($e->getMessage());
            }
        }

        $this->getLogger()->info("All Complete.");
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
