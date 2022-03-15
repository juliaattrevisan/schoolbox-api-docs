<?php

namespace Schoolbox\Admin\Package;

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Admin\Package;
use Schoolbox\User;

class Installer
{
    /**
     * @var Package\Mapper
     */
    private Package\Mapper $packageMapper;

    /**
     * @var ErrorMapper
     */
    private ErrorMapper $errorMapper;

    /**
     * @param Package\Mapper $packageMapper
     */
    public function __construct(Package\Mapper $packageMapper)
    {
        $this->packageMapper = $packageMapper;
        $this->errorMapper = ErrorMapper::getInstance();
    }

    /**
     * @param Package\Model $package
     * @param User $user
     * @throws \Schoolbox\Mapper\Exception
     */
    public function installPackage(Package\Model $package, User $user)
    {
        if (!$package->isInstalled()) {
            include(SITE_root . '/packages/' . $package->getLocation() . '/Installer.php');
            $class = '\\' . $package->getLocation() . '\\Installer';
            $installer = new $class();
            if (!$installer instanceof IPackageInstall) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PROCESSING, null, "Package class not found");
            }

            $db = $this->packageMapper->getDatabase();
            $db->beginTransaction();
            try {
                $installer->install($user);
            } catch (\Throwable $e) {
                $db->rollBack();

                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PROCESSING, $e, sprintf(
                    'Unable to install package, rolling back - %s',
                    $e->getMessage()
                ));
            }
            $db->commit();

            $package->setInstalled(true);
            $this->packageMapper->save($package);
        } else {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PROCESSING, null, "Package is already installed.");
        }
    }
}
