<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Reports;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as CourseUnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as RubricCapabilityMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'curriculumreports';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only staff
         */
        $staffPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF)
        );

        $this->register('GET', '/coverage', 'getCoverage', $staffPerms);
        $this->register('GET', '/coverage/generate', 'generateCoverage', $staffPerms);
        $this->register('GET', '/coverage/usage', 'getCoverageUsage', $staffPerms);
        $this->register('GET', '/coverage/export', 'exportCoverage', $staffPerms);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $curriculumMapper = $this->getMapperFactory()->get(CurriculumMapper::class);
        $rubricCapabilityMapper = $this->getMapperFactory()->get(RubricCapabilityMapper::class);
        $assessmentMapper = $this->getMapperFactory()->get(AssessmentMapper::class);
        $access = $this->getMapperFactory()->get(Access::class);
        return new Controller(
            $curriculumMapper,
            $this->getMapperFactory()->get(CourseSpecificationMapper::class),
            $this->getMapperFactory()->get(CourseUnitSpecificationMapper::class),
            new Helper($curriculumMapper, $rubricCapabilityMapper, $assessmentMapper, $access),
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
