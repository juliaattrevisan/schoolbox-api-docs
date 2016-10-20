<?php
/**
 * Transactor
 *
 * Saves an assessment, figures out a return url, returns a url to which you may redirect
 *
 * Unfortunately returnUrls depend on the saving of an assessment
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use Aura\Router\Router;
use \Schoolbox\Resource\Folder\Model as Folder;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use \Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplementationDataSource;
use \Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use \Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationDataSource;
use \Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
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
     * @var UnitImplementationDataSource
     */
    protected $unitImplementationDataSource;

    /**
     * @var UnitSpecificationDataSource
     */
    protected $unitSpecificationDataSource;

    /**
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * @var Router
     */
    protected $router;

    /**
     * Transactor constructor.
     * @param IAssessable $assessment
     * @param FolderMapper $folderMapper
     * @param UnitImplementationDataSource $unitImplementationDataSource
     * @param UnitSpecificationDataSource $unitSpecificationDataSource
     * @param AssessmentDataSource $assessmentDataSource
     * @param $router
     */
    public function __construct(
        IAssessable $assessment,
        FolderMapper $folderMapper,
        UnitImplementationDataSource $unitImplementationDataSource,
        UnitSpecificationDataSource $unitSpecificationDataSource,
        AssessmentDataSource $assessmentDataSource,
        Router $router
    ) {
        $this->setAssessment($assessment);
        $this->setFolderMapper($folderMapper);
        $this->setUnitImplementationDataSource($unitImplementationDataSource);
        $this->setUnitSpecificationDataSource($unitSpecificationDataSource);
        $this->setAssessmentDataSource($assessmentDataSource);
        $this->setRouter($router);
    }

    /**
     * Figure out a returnUrl when creating/updating assessments
     *
     *
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function transact() {

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
                throw new \InvalidArgumentException('select a valid context');
            }
        }
        /* Otherwise you came from the update screen, just go back there for now
         */
        else {
            $sequence = $this->deriveSaveSequence($this->getAssessmentDataSource(), $this->getAssessment(), $assessmentContext);
            $this->getAssessmentDataSource()->save($this->getAssessment(), $sequence);

            $returnUrl = $this->getRouter()->generate('learningAssessment.getUpdateAssessment', ['assessmentId' => $this->getAssessment()->getId()]);
        }

        /* The conclusion of how $returnUrl was derived
         */
        return [
            'assessment' => $this->getAssessment(),
            'returnUrl' => $returnUrl
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
        $sequence = $this->deriveSaveSequence($this->getAssessmentDataSource(), $this->getAssessment(), $context);
        $this->getAssessmentDataSource()->save($this->getAssessment(), $sequence);

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
        $context   = $this->getUnitImplementationDataSource()->findById($this->getContextId());
        $sequence = $this->deriveSaveSequence($this->getAssessmentDataSource(), $this->getAssessment(), $context);
        $this->getAssessmentDataSource()->save($this->getAssessment(), $sequence);

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
        $context   = $this->getUnitSpecificationDataSource()->findById($this->getContextId());
        $sequence = $this->deriveSaveSequence($this->getAssessmentDataSource(), $this->getAssessment(), $context);
        $this->getAssessmentDataSource()->save($this->getAssessment(), $sequence);

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
        $context   = $this->getAssessmentDataSource()->findById($this->getContextId());
        $sequence = $this->deriveSaveSequence($this->getAssessmentDataSource(), $this->getAssessment(), $context);
        $this->getAssessmentDataSource()->save($this->getAssessment(), $sequence);

        return "/homepage/{$context->getId()}";
    }

    /**
     * Figure out a sequence
     *
     * @param $context
     * @return int
     */
    protected function deriveSaveSequence($context)
    {
        $sequence = 1;
        foreach ($this->getAssessmentDataSource()->findAllForContext($context, ['order' => ['sequence' => 'asc']]) as $otherAssessment) {
            if ($otherAssessment->getId() == $this->getAssessment()->getId()) {
                break;
            }
            $sequence++;
        }

        return $sequence;
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
     * @return UnitImplementationDataSource
     */
    protected function getUnitImplementationDataSource()
    {
        return $this->unitImplementationDataSource;
    }

    /**
     * @param UnitImplementationDataSource $unitImplementationDataSource
     */
    protected function setUnitImplementationDataSource($unitImplementationDataSource)
    {
        $this->unitImplementationDataSource = $unitImplementationDataSource;
    }

    /**
     * @return UnitSpecificationDataSource
     */
    protected function getUnitSpecificationDataSource()
    {
        return $this->unitSpecificationDataSource;
    }

    /**
     * @param UnitSpecificationDataSource $unitSpecificationDataSource
     */
    protected function setUnitSpecificationDataSource($unitSpecificationDataSource)
    {
        $this->unitSpecificationDataSource = $unitSpecificationDataSource;
    }

    /**
     * @return AssessmentDataSource
     */
    protected function getAssessmentDataSource()
    {
        return $this->assessmentDataSource;
    }

    /**
     * @param AssessmentDataSource $assessmentDataSource
     */
    protected function setAssessmentDataSource($assessmentDataSource)
    {
        $this->assessmentDataSource = $assessmentDataSource;
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