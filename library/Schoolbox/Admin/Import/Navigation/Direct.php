<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Navigation;

use Aura\Router\Generator;
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
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }


    /**
     * {@inheritDoc}
     *
     * @param Generator $routeGenerator
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function buildBreadcrumbs(Generator $routeGenerator): array
    {
        return [
            ['Administration', '/adminv2/home'],
            ['Direct Import', $routeGenerator->generate('adminImportIndex.getDirectIndex')],
        ];
    }


    /**
     * {@inheritDoc}
     *
     * @param Generator $routeGenerator
     * @param string    $currentPage
     * @return Navigator
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function buildNavigation(Generator $routeGenerator, string $currentPage = ''): Navigator
    {
        $args = [
            new Node(
                'User Accounts',
                $routeGenerator->generate('adminImportUserAccount.getUpload'),
                $currentPage === 'adminImportUserAccount'
            ),
            new Node(
                'User Photos',
                $routeGenerator->generate('adminImportPhotos.getIndex'),
                $currentPage === 'adminImportPhotos'
            ),
            new Node(
                'Folders',
                $routeGenerator->generate('adminImportFolder.getIndex'),
                $currentPage === 'adminImportFolder'
            ),
        ];

        /* Optional Pastoral Module
         */
        if ($this->configLoader->get('pastoral_enabled')) {
            $rosettaStone = (new RosettaStoneFactory())->build(L10nMapper::getInstance());

            $args[] = new Node(
                $rosettaStone->translate('pastoralCare', false, true) . ' Records',
                $routeGenerator->generate('adminImportPastoral.getIndex'),
                $currentPage === 'adminImportPastoral'
            );
        }

        /* Folders
         */

        return new Navigator($args);
    }
}
