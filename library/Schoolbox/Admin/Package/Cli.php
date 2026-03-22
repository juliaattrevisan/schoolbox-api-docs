<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Schoolbox\Application\CliTool;
use Schoolbox\Admin\Package;
use Schoolbox\UserMapper;

class Cli extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * @var Package\Mapper
     */
    private Package\Mapper $packageMapper;

    /**
     * Alaress User
     * @var \Schoolbox\User $user
     */
    private \Schoolbox\User $user;

    //nicely format the output
    private int $maxHiddenLength    = 60;
    private int $maxInstalledLength = 60;
    private int $maxTitleLength     = 60;

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
        $this->packageMapper = Package\Mapper::getInstance();
        $userMapper = UserMapper::getInstance();
        $this->user = $userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);
        $this->maxHiddenLength = strlen('Not Hidden\  ');
        $this->maxInstalledLength = strlen('Not Installed\    ');

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Displays a list of all available packages, and will install a selected package

Usage: tools/package/install.php [-v] [-p]
        -p Actually install the package, else just display what would occur
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }

        if (!$this->perform()) {
            $this->getLogger()->alert("Preview Mode");
        } else {
            $this->getLogger()->alert("Action Mode");
        }

        /**
         * run the interaction loop
         * show list of packages, and their status, then allow the user to exit, or install a package.
         * if they install a package, loop to allow them to install another.
         */
        $this->choiceInstall();
    }
    private function choiceInstall()
    {
        $allPackages  = $this->packageMapper->findAll();
        $listPackages = [];
        /**
         * list all available packages
         */
        /** @var Package\Model $package */
        foreach ($allPackages as $package) {
            $listPackages[$package->getId()] = "\t";
            $listPackages[$package->getId()] .= str_pad("{$package->getTitle()}", $this->maxTitleLength);
            $listPackages[$package->getId()] .= str_pad("{$package->getInstalledString()}", $this->maxInstalledLength);
            $listPackages[$package->getId()] .= str_pad("{$package->getHiddenString()}", $this->maxHiddenLength);
        }
        $listPackages[0] = "Exit";

        /**
         * get choice for which package we want to install...
         */
        $packageId = $this->getChoice("Pick which Package you want to install", $listPackages);
        if (0 === $packageId) {
            $this->getLogger()->info("Quitting");
            exit;
        }
        $this->getLogger()->info("Selected Id: {$packageId}");
        // Find the package with the matching database ID
        $package = null;
        foreach ($allPackages as $pkg) {
            if ($pkg->getId() === $packageId) {
                $package = $pkg;
                break;
            }
        }
        if ($package === null) {
            $this->getLogger()->error("Package with ID {$packageId} not found");
            $this->choiceInstall();
            return;
        }
        $this->getLogger()->info("Found {$package->getTitle()}");

        /**
         * package found, is it already installed?
         */
        if (true === $package->isInstalled()) {
            $this->getLogger()->error("Package is already installed.");
            $this->choiceInstall();
        }

        /**
         * Lets attempt to install the package
         */
        try {
            /**
             * lets just say we are alaress, and installing as such
             */
            if ($this->perform()) {
                $packageInstaller = new Package\Installer($this->packageMapper);
                $packageInstaller->installPackage($package, $this->user);
                $this->getLogger()->alert("Installed {$package->getTitle()}");
            } else {
                $this->getLogger()->alert("Would have installed {$package->getTitle()}");
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            if (null !== $e->getPrevious()) {
                $this->getLogger()->error("Previous Error:{$e->getPrevious()->getMessage()}");
            }
            $this->getLogger()->error("Current Error:{$e->getMessage()}");
        }
        $this->choiceInstall();
    }
}
