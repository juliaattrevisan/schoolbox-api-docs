<?php
/**
 * Transactor
 *
 * Saves an assessment, figures out a return url, returns a url to which you may redirect
 *
 * Unfortunately returnUrls depend on the saving of an assessment
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Aura\Router\Router;
use \Schoolbox\Resource\Folder\Model as Folder;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use \Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use \Schoolbox\Mapper\Exception as MapperException;

class Transactor
{
    /**
     * @var IAssessable
     */
    protected $assessment;

    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;

    /**
     * @var UnitSpecificationMapper
     */
    private $unitSpecificationMapper;

    /**
     * @var AssessmentMapperProxy
     */
    protected $assessmentMapperProxy;

    /**
     * @var Router
     */
    protected $router;

    /**
     * Transactor constructor.
     * @param FolderMapper $folderMapper
     * @param UnitImplementationMapper $unitImplementationMapper
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param $router
     */
    public function __construct(
        FolderMapper $folderMapper,
        UnitImplementationMapper $unitImplementationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        Router $router
    ) {
        $this->setFolderMapper($folderMapper);
        $this->setUnitImplementationMapper($unitImplementationMapper);
        $this->setUnitSpecificationMapper($unitSpecificationMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setRouter($router);
    }


    /**
     * Figure out a returnUrl when creating/updating assessments
     *
     *
     * @param IAssessable $assessment
     * @return array
     */
    public function transact(IAssessable $assessment)
    {
        $this->setAssessment($assessment);

        // Verify that the context of the assessment is valid
        // TODO replace returnUrl values once I know what they should actually be
        $assessmentContext = $this->getAssessment()->getContext();

        /* If you followed a creational link from a particular place,
         * go back to that place
         */
        if (!$this->getAssessment()->getId()) {
            try {
                if ($assessmentContext instanceof Folder) {
                    $returnUrl = $this->transactFolderContext();
                }
                elseif ($assessmentContext instanceof UnitImplementation) {
                    $returnUrl = $this->transactUnitImplementationContext();
                }
                elseif ($assessmentContext instanceof UnitSpecification) {
                    $returnUrl = $this->transactUnitSpecificationContext();
                }
                elseif ($assessmentContext instanceof Project) {
                    $returnUrl = $this->transactProjectContext();
                } else {
                    throw new \InvalidArgumentException('select a valid context');
                }
            } catch (MapperException $e) {
                throw new \InvalidArgumentException('select a valid context', 0, $e);
            }
        }
        /* Otherwise you came from the update screen, just go back there for now
         */
        else {
            $this->getAssessmentMapperProxy()->save($this->getAssessment());

            $returnUrl = $this->getRouter()->generate('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $this->getAssessment()->getId()]);
        }

        /* The conclusion of how $returnUrl was derived
         */
        return [
            'assessment' => $this->getAssessment(),
            Redirect::RETURN_URL => $returnUrl
        ];
    }

    /**
     * Transact from Folder context
     *
     * @return string
     */
    private function transactFolderContext()
    {

        /* TODO: Pass contextId as a param into this method
         * It should be obtained from the routes
         * You only get contextId when you're Creating, not updating
         */
        $context   = $this->getFolderMapper()->findById($this->getContextId());

        $this->getAssessmentMapperProxy()->save($this->getAssessment());

        $returnUrl = "/homepage/{$context->getId()}";

        return $returnUrl;
    }

    /**
     * Transact from Unit Implementation context
     *
     * @return string
     * @throws MapperException\Deleted
     */
    private function transactUnitImplementationContext()
    {
        $context   = $this->getUnitImplementationMapper()->findById($this->getContextId());
        $this->getAssessmentMapperProxy()->save($this->getAssessment());

        return "/homepage/{$context->getId()}";
    }

    /**
     * Transact from Unit Specification context
     *
     * @return string
     * @throws MapperException\Deleted
     */
    private function transactUnitSpecificationContext()
    {
        // The unit spec doesn't contain a reference back to its course spec.
        // It should, or I should be able to find one.
        $context   = $this->getUnitSpecificationMapper()->findById($this->getContextId());
        $this->getAssessmentMapperProxy()->save($this->getAssessment());

        return $this->getRouter()->generate('course.modifyCourse', ['id' => $context->getCourse()->getId()]);
    }

    /**
     * Transact from Project context
     *
     * Check that you don't spawn a Project in a Project
     *
     * @throws MapperException\Deleted
     */
    private function transactProjectContext()
    {
        if ($this->getAssessment() instanceof Project) {
            throw new \DomainException('You cannot create nested projects.');
        }

        // TODO figure out what to do about this
        $this->getAssessmentMapperProxy()->save($this->getAssessment());

        return "/homepage/{$this->assessment->getContext()->getId()}";
    }

     /* Getters/Setters
     */

    /**
     * @return int
     */
    protected function getContextId()
    {
        return $this->getAssessment()->getContext()->getId();
    }

    /**
     * @return IAssessable
     */
    protected function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param IAssessable $assessment
     */
    protected function setAssessment($assessment)
    {
        $this->assessment = $assessment;
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     */
    protected function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return UnitImplementationMapper
     */
    protected function getUnitImplementationMapper()
    {
        return $this->unitImplementationMapper;
    }

    /**
     * @param UnitImplementationMapper $unitImplementationMapper
     */
    protected function setUnitImplementationMapper($unitImplementationMapper)
    {
        $this->unitImplementationMapper = $unitImplementationMapper;
    }

    /**
     * @return UnitSpecificationMapper
     */
    protected function getUnitSpecificationMapper()
    {
        return $this->unitSpecificationMapper;
    }

    /**
     * @param UnitSpecificationMapper $unitSpecificationMapper
     */
    protected function setUnitSpecificationMapper($unitSpecificationMapper)
    {
        $this->unitSpecificationMapper = $unitSpecificationMapper;
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     */
    protected function setAssessmentMapperProxy($assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
    }

    /**
     * @return Router
     */
    protected function getRouter()
    {
        return $this->router;
    }

    /**
     * @param Router $router
     */
    protected function setRouter($router)
    {
        $this->router = $router;
    }
}
