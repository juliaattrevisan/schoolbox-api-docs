<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
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
        $this->response = $this->responseFromTemplate(
            '@adminPackage/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminPackage.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminPackage.getIndex')),
                'packages'    => $this->packageMapper->findAvailablePackages(),
            ]
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
        $post = $this->requestPost;

        $package = $this->packageMapper->findById($post['id']);

        $packageInstaller = new Installer($this->packageMapper);
        $packageInstaller->installPackage($package, $this->getUser());

        $this->response = $this->responseAsJson(['success' => true]);
    }
}
