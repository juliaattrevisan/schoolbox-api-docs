<?php

namespace Schoolbox\Admin\Package;

use ErrorException;
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
            /** @var IPackageInstall $installer **/
            $installer = new $class();
            if (!$installer instanceof IPackageInstall) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_INSTALLATION_FAILED, null, "Package installer not found");
            }
            /**
             * Check for package requirements
             */
            if (!empty($installer->getRequiredPackages())) {
                foreach ($installer->getRequiredPackages() as $reqPackage) {
                    /** @var Package\Model $tmp */
                    $tmp = $this->packageMapper->findOneByColumn('location', $reqPackage);
                    // check if the package is installed
                    if (!$tmp->isInstalled()) {
                        throw $this->errorMapper->generateException(
                            ErrorMapper::ERROR_INSTALLATION_FAILED,
                            null,
                            sprintf("This package depends on %s, please install that first", $tmp->getTitle())
                        );
                    }
                }
            }
            //set the package as installed, to stop multiple calls...
            $package->setInstalled(true);
            $this->packageMapper->save($package);

            $db = $this->packageMapper->getDatabase();
            $db->beginTransaction();

            // Install custom error handler here:
            // it mirrors the CLI context's error handler, which raises
            // exceptions on errors or notices.
            // Restore the previous handler once done.
            $previousErrorHandler = $this->installErrorHandler();
            try {
                $installer->install($user);
            } catch (\Throwable $e) {
                $db->rollBack();

                //mark the package as not installed to allow the user to attempt installing again.
                $package->setInstalled(false);
                $this->packageMapper->save($package);

                throw $this->errorMapper->generateException(ErrorMapper::ERROR_INSTALLATION_FAILED, $e, $e->getMessage());
            } finally {
                set_error_handler($previousErrorHandler);
            }
            $db->commit();
        } else {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INSTALLATION_FAILED, null, "Package is already installed.");
        }
    }

    /**
     * Installs an error handler which raises an `ErrorException` on receiving
     * a warning or notice. (This mimics the CLI context's error handler.)
     *
     * @return callable|null ($errNum: int, $errStr: string, $errFile: string, $errLine: int)
     *                       The previous error handler, for re-installation
     *                       once the import is complete.
     * @noinspection PhpDocMissingThrowsInspection - this method doesn't throw
     *               an exception, but the new error handler might.
     */
    private function installErrorHandler(): ?callable
    {
        $errorHandler = set_error_handler(null);
        set_error_handler(
            static function ($errNum, $errStr, $errFile, $errLine) use ($errorHandler): void {
                // Ignore this error if it isn't reported
                if (!(error_reporting() & $errNum)) {
                    return;
                }

                // Notices and warnings: raise an error exception
                if (in_array($errNum, [E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING])) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);
                }

                // Fall back to the previous error handler
                if (is_callable($errorHandler)) {
                    $errorHandler($errNum, $errStr, $errFile, $errLine);
                }
            }
        );
        return $errorHandler;
    }
}
