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
use Schoolbox\Mapper\Exception as MapperException;

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
        /* Keep an eye out on Granular Permissions epic SA-18424
         * Hopefully there isn't a service permission that affects us here
         * and we can continue to assume Superusers Only
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $preloadField = function ($params) {
            return $this->preloadField($params);
        };


        $this->register('GET', '', 'getIndex', $perms);
        $this->register('POST', '/field/{roleType}/{fieldId}/remove', 'postFieldRemove', $perms, $preloadField);
        $this->register('POST', '/field/{roleType}/{fieldId}/add', 'postFieldAdd', $perms, $preloadField);
        $this->register('POST', '/field/{roleType}/{fieldId}/settings', 'postFieldSettings', $perms, $preloadField);
        $this->register('POST', '/field/{roleType}/sequence', 'postFieldSequence', $perms);
        $this->register('POST', '/field/{roleType}/nameformat', 'postNameFormat', $perms);
    }


    /**
     * Preload the profile field
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws NotFound
     */
    private function preloadField(array $params): array
    {
        try {
            $field = $this->getFieldMapper()->findOneByColumnList([
                'roleType' => $params['roleType'],
                'field' => $params['fieldId'],
            ]);
        } catch (MapperException $e) {
            throw new NotFound(sprintf(
                'Unknown profile field %s in roletype %s',
                $params['fieldId'],
                $params['roleType']
            ), 400, $e);
        }

        return [
            'roleType' => $params['roleType'],
            'field' => $field,
        ];
    }


    public function getFieldMapper(): \Schoolbox\Admin\Profile\Editor\Field\Mapper
    {
        return $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Field\Mapper::class);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Field\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Field\Availability\Campus\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Field\Visibility\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\CampusMapper::class)
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
