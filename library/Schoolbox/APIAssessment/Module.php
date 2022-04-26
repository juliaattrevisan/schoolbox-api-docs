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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\UserMapper;
use DomainException;
use LogicException;
use Schoolbox\Application\Exception as ApplicationException;
use UnexpectedValueException;

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

        $this->register('GET', '/{assessmentId}', 'apiAssessmentGet', $hasApiAccess);
        $this->register('GET', '', 'apiAssessmentGetAll', $hasApiAccess);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(

        );
    }
}
