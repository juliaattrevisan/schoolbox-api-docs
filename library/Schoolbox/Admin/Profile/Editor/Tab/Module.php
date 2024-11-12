<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Tab;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Profile\Editor\Payload;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminProfileEditorTab';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
            )
        );

        $preloadTab = function ($params) {
            return $this->preloadTab($params);
        };

        $preloadRoleType = function ($params) {
            return $this->preloadRoleType($params);
        };

        $this->register('POST', '/{roleType}/{tabId}/settings', 'postSettings', $perms, $preloadTab);
        $this->register('POST', '/{roleType}/sequence', 'postSequence', $perms, $preloadRoleType);
    }


    /**
     * Preload the profile tab
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws NotFound
     * @throws \InvalidArgumentException
     */
    private function preloadTab(array $params): array
    {
        $roleType = $this->preloadRoleType($params);

        try {
            $tab = $this->getTabMapper()->findOneByColumnList([
                'roleType' => $params['roleType'],
                'tab' => $params['tabId'],
            ]);
        } catch (MapperException $e) {
            throw new NotFound(sprintf(
                'Unknown profile tab %s in roletype %s',
                $params['tabId'],
                $params['roleType']
            ), 400, $e);
        }

        return [
            'roleType' => $roleType['roleType'],
            'tab' => $tab,
        ];
    }


    /**
     * Check that a valid and supported roletype was provided
     *
     * @param array $params
     * @return array
     * @throws NotFound
     */
    private function preloadRoleType(array $params): array
    {
        $roleType = $params['roleType'] ?? null;

        if (!isset(Payload::CONFIG_ROLETYPE[$roleType])) {
            throw new NotFound('Roletype not provided', 400);
        }

        return [
            'roleType' => $params['roleType'],
        ];
    }


    /**
     * @return Mapper
     * @throws \InvalidArgumentException
     */
    public function getTabMapper(): Mapper
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(Availability\Campus\Mapper::class),
            $this->getMapperFactory()->get(Visibility\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\CampusMapper::class),
        );
    }
}
