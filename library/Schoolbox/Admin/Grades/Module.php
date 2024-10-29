<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Grades;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Closure;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeLevelMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminGrades';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /**
         * This rule is used for all CRUD operations currently
         */
        $viewGradesRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
            )
        );

        /**
         * default landing page
         */
        $this->register(self::METHOD_GET, '', 'getIndex', $viewGradesRule);

        /**
         * handle upsert of grade groups and their associated levels
         */
        $this->register(self::METHOD_POST, '', 'postSaveGrades', $viewGradesRule);

        /**
         * soft delete a grade levels group
         */
        $this->register(self::METHOD_DELETE, '/group/{groupId:\d+}', 'deleteGradeGroup', $viewGradesRule, Closure::fromCallable([$this, 'preloadGradeGroup']));

        /**
         * soft delete a grade level
         */
        $this->register(self::METHOD_DELETE, '/level/{levelId:\d+}', 'deleteGradeLevel', $viewGradesRule, Closure::fromCallable([$this, 'preloadGradeLevel']));
    }

    /**
     * @inheritDoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(GradeLevelMapper::class),
            $this->getMapperFactory()->get(GradeLevelGroupMapper::class)
        );
    }

    /**
     * Preload a GradeLevelGroup instance
     *
     * @param array $params
     * @return array
     * @throws Exception
     * @throws Deleted
     */
    private function preloadGradeGroup(array $params): array
    {
        return [
            'gradeGroup' => $this->getMapperFactory()
                                 ->get(GradeLevelGroupMapper::class)
                                 ->findById($params['groupId']),
        ];
    }

    /**
     * Preload a GradeLevel instance
     *
     * @param array $params
     * @return array
     * @throws Exception
     * @throws Deleted
     */
    private function preloadGradeLevel(array $params): array
    {
        return [
            'gradeLevel' => $this->getMapperFactory()
                                 ->get(GradeLevelMapper::class)
                                 ->findById($params['levelId']),
        ];
    }
}
