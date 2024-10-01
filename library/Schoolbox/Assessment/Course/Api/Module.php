<?php

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Api;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'courseApi';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only superuser should have access to the data
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                //Staff and Superusers will have access to the endpoint
                //It might change once the granular permission is implemented
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
            )
        );

        $this->register('GET', '', 'searchCourses', $getPerms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            CourseSpecificationMapper::getInstance(),
            FolderMapper::getInstance(),
            Access::getInstance(),
            UnitSpecificationMapper::getInstance(),
        );
    }
}
