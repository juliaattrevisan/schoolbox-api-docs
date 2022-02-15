<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Nesa;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerInterface;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper;
use Schoolbox\ProfessionalDevelopment\Mapper as ProfessionalDevelopmentMapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'helpNesa';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $nesa = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/report', 'getNesa', $nesa, null);
        $this->register('POST', '/report', 'postNesa', $nesa, null);
        $this->register('POST', '/report/csv', 'postNesaCsv', $nesa, null);
    }

    /**
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): ?Base
    {
        return $this->installControllerDependencies(
            new Controller(
                $this->getMapperFactory()->get(ErrorMapper::class),
                $this->getMapperFactory()->get(CourseSpecificationMapper::class),
                $this->getMapperFactory()->get(RubricDataSource::class),
                $this->getMapperFactory()->get(ProfessionalDevelopmentMapper::class),
                $this->getMapperFactory()->get(UserMapper::class)
            ),
            $container
        );
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
