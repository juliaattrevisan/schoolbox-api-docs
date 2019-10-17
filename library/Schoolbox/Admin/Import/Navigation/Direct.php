<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Navigation;

use Aura\Router\Router;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;

/**
 * Class Direct
 *
 * Builds navigation for Direct Import context
 *
 * @package Schoolbox\Admin\Import\Navigation
 */
class Direct implements INavigation
{

    /**
     * @var IConfigLoader
     */
    private $configLoader;


    /**
     * Direct constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader) {
        $this->configLoader = $configLoader;
    }


    /**
     * {@inheritDoc}
     *
     * @param Router $router
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function buildBreadcrumbs(Router $router): array
    {
        return [
            ['Administration', '/adminv2/home'],
            ['Direct Import', $router->generate('adminImportIndex.getDirectIndex')],
        ];
    }


    /**
     * {@inheritDoc}
     *
     * @param Router $router
     * @param string $currentPage
     * @return Navigator
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function buildNavigation(Router $router, string $currentPage = ''): Navigator
    {
        $args = [
            new Node(
                'User Accounts',
                $router->generate('adminImportUserAccount.getUpload'),
                $currentPage === 'adminImportUserAccount'
            ),
            //            new Node(
            //                'User Photos',
            //                $router->generate('adminImportPhotos.getIndex'),
            //                $currentPage === 'adminImportPhotos'
            //            ),
        ];

        /* Optional Pastoral Module
         */
        if ($this->configLoader->get('pastoral_enabled')) {
            $rosettaStone = (new RosettaStoneFactory())->build(L10nMapper::getInstance());

            $args[] = new Node(
                $rosettaStone->translate('pastoralCare', false, true) . ' Records',
                $router->generate('adminImportPastoral.getIndex'),
                $currentPage === 'adminImportPastoral'
            );
        }

        /* Folders
         */

        return new Navigator($args);
    }
}
