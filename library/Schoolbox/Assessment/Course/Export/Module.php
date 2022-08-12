<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Export;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Overwriter;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Module for exporting assessments into courses.
 */
class Module extends \Alaress\Module\Module
{
    /**
     * @inheritdoc
     */
    protected $basename = 'courseExport';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        // Permissions:
        // * User must be able to create an assessment in the spec folder
        $permissions = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(
                    $this->getMapperFactory()->get(Access::class),
                    static function (array $params): FolderModel {
                        if (isset($params['unitSpec']) && $params['unitSpec'] instanceof UnitSpec\Model) {
                            return $params['unitSpec']->getFolder();
                        }
                        throw new NotFound('No context preloaded');
                    }
                )
            )
        );

        // Permissions:
        // * must be able to write both assessment and course spec
        $this->register(
            self::METHOD_POST,
            '/unitspecs/{unitSpecId:\d+}/assessment/{assessmentId:\d+}',
            'postAssessmentIntoUnit',
            $permissions,
            $this->buildPreload(self::METHOD_POST),
        );
        $this->register(
            self::METHOD_PUT,
            '/unitspecs/{unitSpecId:\d+}/assessment/{assessmentId:\d+}',
            'putAssessmentIntoUnit',
            $permissions,
            $this->buildPreload(self::METHOD_PUT),
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getAssessmentDataSource(),
            new Replicator(
                $this->getAssessmentDataSource(),
                $this->getMapperFactory()->get(Mapper::class),
                $this->getMapperFactory()->get(RubricDataSource::class),
            ),
            new Overwriter(
                $this->getAssessmentDataSource(),
                $this->getMapperFactory()->get(Mapper::class),
                $this->getMapperFactory()->get(RubricDataSource::class),
            ),
        );
    }

    /**
     * Return the assessment data source.
     *
     * @return MapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getAssessmentDataSource(): MapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * Build a preload callback.
     *
     * The callback varies based on whether the method is a POST or PUT:
     * * if POST, the assessment MUST NOT have a copy already in the spec:
     *   the PUT route must be used instead to update the copy
     * * if PUT, the assessment MUST have a copy already in the spec:
     *   the POST route must be used to create a copy in the spec
     *
     * @param string $method
     * @return Closure
     */
    private function buildPreload(string $method): Closure
    {
        $callable = function ($params) use ($method) {
            try {
                $unitSpec = $this->getMapperFactory()->get(UnitSpec\Mapper::class)
                                 ->findById($params['unitSpecId']);
            } catch (MapperException) {
                throw new NotFound("Unit Spec not found. id: {$params['unitSpecId']}");
            }

            try {
                $assessment = $this->getAssessmentDataSource()->findById($params['assessmentId']);
            } catch (MapperException) {
                throw new NotFound("Assessment not found. id: {$params['assessmentId']}");
            }

            // Does assessment already exist in the spec it's being moved into?
            $specExists = false;
            if ($assessment->getSpecificationAssessment()) {
                $specBaseContext = $assessment->getSpecificationAssessment()->getBaseContext();
                if (
                    $specBaseContext instanceof UnitSpec\Model
                    && $unitSpec->getId() === $specBaseContext->getId()
                ) {
                    $specExists = true;
                }
            }
            if ($specExists && $method === self::METHOD_POST) {
                throw new InvalidPath(
                    sprintf(
                        'A copy of Assessment #%d already exists in unit spec #%d: use the PUT route to update the copy instead',
                        $assessment->getId(),
                        $unitSpec->getId(),
                    )
                );
            }
            if (!$specExists && $method === self::METHOD_PUT) {
                throw new InvalidPath(
                    sprintf(
                        'A copy of Assessment #%d does not exist in unit spec #%d: use the POST route to create a copy instead',
                        $assessment->getId(),
                        $unitSpec->getId(),
                    )
                );
            }

            return [
                'unitSpec'   => $unitSpec,
                'assessment' => $assessment,
            ];
        };
        return Closure::fromCallable($callable);
    }
}
