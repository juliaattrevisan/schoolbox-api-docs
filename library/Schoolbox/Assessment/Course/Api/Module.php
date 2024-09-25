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
        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        /* Only superusers can receive data from the form
         */
        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

//        $preloadUser = function ($params) {
//            return $this->preloadUser($params);
//        };

        $this->register('GET', '', 'searchCourses', $getPerms);
//        $this->register('POST', '', 'postExample', $postPerms, null);
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
