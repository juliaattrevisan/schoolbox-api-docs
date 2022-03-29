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

        $preloadAssessment             = function ($params) {
            return $this->preloadAssessment($params);
        };

        $this->register('GET', '/{assessmentId}', 'apiAssessmentGet', $hasApiAccess, $preloadAssessment);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * Convert route params into an assessment object
     *
     * @param array     $params
     * @return array$assessable
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    protected function preloadAssessment(array $params): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException\Deleted $e) {
            $assessment = $e->getDeletedObject();
        } catch (MapperException $e) {
            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
            throw new SchoolboxException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }



        return [
            'assessment' => $assessment
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

    /**
     * @return object|AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }
}
