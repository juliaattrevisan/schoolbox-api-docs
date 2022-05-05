<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'apiAssessment';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Only superusers can access the Groups API
         */
        $hasApiAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '/{assessmentId}', 'apiAssessmentGet', $hasApiAccess);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws SchoolboxException
     */
    private function preloadUser(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (MapperException $e) {
            throw new SchoolboxException('User Does not Exist');
        }

        return [
            'user' => $user,
        ];
    }

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return GroupMapper
     */
    protected function getGroupMapper(): GroupMapper
    {
        return $this->getMapperFactory()->get(GroupMapper::class);
    }
}
