<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminProfileEditor';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser(),
        );

        $preloadRoleType = function ($params) {
            return $this->preloadRoleType($params);
        };

        $this->register('GET', '', 'getIndex', $perms);
        $this->register('POST', '/{roleType}/nameformat', 'postNameFormat', $perms, $preloadRoleType);
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Field\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Tab\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\CampusMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Config\ConfigMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Profile\Button\Mapper::class)
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
