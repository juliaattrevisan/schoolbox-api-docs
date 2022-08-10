<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Export;

use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
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
        $preload     = function ($params) {
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

            return [
                'unitSpec'   => $unitSpec,
                'assessment' => $assessment,
            ];
        };

        // Permissions:
        // * must be able to write both assessment and course spec
        $this->register(
            self::METHOD_POST,
            '/unitspecs/{unitSpecId:\d+}/assessment/{assessmentId:\d+}',
            'postAssessmentIntoUnit',
            $permissions,
            $preload,
        );
        $this->register(
            self::METHOD_PUT,
            '/unitspecs/{unitSpecId:\d+}/assessment/{assessmentId:\d+}',
            'putAssessmentIntoUnit',
            $permissions,
            $preload,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * Return the assessment data source.
     *
     * @return MapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentDataSource(): MapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }
}
