<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;

class Controller extends Base
{
    private const PAGE_CURRENT = 'package';

    /**
     * @var Mapper
     */
    private Mapper $packageMapper;

    /**
     * @param Mapper $packageMapper
     */
    public function __construct(Mapper $packageMapper)
    {
        $this->packageMapper = $packageMapper;
    }

    /**
     * Get index all available packages and their installation state
     *
     * @api   GET /adminv2/package
     * @since 20.2
     */
    public function getIndex(): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminPackage/index.twig',
                [
                    'pageCurrent' => self::PAGE_CURRENT,
                    'packages'    => $this->packageMapper->findAll(),
                ]
            )
        );
    }


    /**
     * Install a package
     *
     * @api   POST /adminv2/package/install
     * @since 20.2
     */
    public function installPackage(): void
    {
        $post = $this->getRequest()->post;

        $package = $this->packageMapper->findById($post['id']);

        if (!$package->isInstalled()) {
            include(SITE_root . '/packages/' . $package->getLocation() . '/Installer.php');
            $class = '\\'.$package->getLocation().'\\Installer';
            $installer = new $class();

            $db = $this->packageMapper->getDatabase();
            $db->beginTransaction();
            try {
                $installer->install($this->getUser());
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $this->generateException(ErrorMapper::ERROR_PROCESSING, $e, "Unable to install package, rolling back.");
            }
            $db->commit();

            $package->setInstalled(true);
            $this->packageMapper->save($package);
        } else {
            throw $this->generateException(ErrorMapper::ERROR_PROCESSING, null, "Package is already installed.");
        }

        $this->getResponse()->content->set($this->buildJsonOutput(['success' => true]));
    }
}
