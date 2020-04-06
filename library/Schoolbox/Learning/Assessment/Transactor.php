<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use UnexpectedValueException;

/**
 * Class Transactor
 *
 * Saves an assessment, figures out a return url, returns a url to which you may redirect
 *
 * Unfortunately returnUrls depend on the saving of an assessment
 *
 * @package Schoolbox\Learning\Assessment
 */
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
     * @var DueWork\Response\File\Plagscan\Mapper
     */
    protected $plagscanMapper;


    /**
     * @var SingleFile\Mapper
     */
    protected $submissionFileMapper;


    /**
     * @var Router
     */
    protected $router;


    /**
     * Transactor constructor.
     *
     * @param FolderMapper                          $folderMapper
     * @param UnitImplementationMapper              $unitImplementationMapper
     * @param UnitSpecificationMapper               $unitSpecificationMapper
     * @param AssessmentMapperProxy                 $assessmentMapperProxy
     * @param DueWork\Response\File\Plagscan\Mapper $plagscanMapper
     * @param SingleFile\Mapper                     $submissionFileMapper
     * @param Router                                $router
     */
    public function __construct(
        FolderMapper $folderMapper,
        UnitImplementationMapper $unitImplementationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        DueWork\Response\File\Plagscan\Mapper $plagscanMapper,
        SingleFile\Mapper $submissionFileMapper,
        Router $router
    ) {
        $this->folderMapper = $folderMapper;
        $this->unitImplementationMapper = $unitImplementationMapper;
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->plagscanMapper = $plagscanMapper;
        $this->submissionFileMapper = $submissionFileMapper;
        $this->router = $router;
    }


    /**
     * Figure out a returnUrl when creating/updating assessments
     *
     * @param IAssessable $assessment
     * @param array       $before
     * @return array
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function transact(IAssessable $assessment, array $before = []): array
    {
        $this->assessment = $assessment;

        // Verify that the context of the assessment is valid
        // TODO replace returnUrl values once I know what they should actually be
        $assessmentContext = $this->assessment->getContext();

        /* If you followed a creational link from a particular place,
         * go back to that place
         */
        if (!$this->assessment->getId()) {
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
                    throw new InvalidArgumentException('Please select a valid context');
                }
            } catch (MapperException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }
        }
        /* Otherwise you came from the update screen, just go back there for now
         */
        else {
            $this->assessmentMapperProxy->save($this->assessment);

            /* Due Work has a special thing going on where it can upload to Plagscan
             *
             * If Similarity Check was previously disabled, but has now been enabled,
             * Then attempt to retrofit all the previous submissions
             */
            if (($this->assessment instanceof DueWork\Model) && !$before['assessment']['similarityCheckEnabled'] && $assessment->getSimilarityCheckEnabled()) {
                (new DueWork\Response\File\Plagscan\Retrofit($this->plagscanMapper, $this->submissionFileMapper))->retrofit($assessment->getId());
            }

            $returnUrl = $this->router->generate('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $this->assessment->getId()]);
        }

        /* The conclusion of how $returnUrl was derived
         */
        return [
            'assessment' => $this->assessment,
            Redirect::RETURN_URL => $returnUrl
        ];
    }

    /**
     * Transact from Folder context
     *
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    private function transactFolderContext(): string
    {

        /* TODO: Pass contextId as a param into this method
         * It should be obtained from the routes
         * You only get contextId when you're Creating, not updating
         */
        $context = $this->folderMapper->findById($this->getContextId());

        $this->assessmentMapperProxy->save($this->assessment);

        return "/homepage/{$context->getId()}";
    }


    /**
     * Transact from Unit Implementation context
     *
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function transactUnitImplementationContext(): string
    {
        $context = $this->unitImplementationMapper->findById($this->getContextId());

        $this->assessmentMapperProxy->save($this->assessment);

        return "/homepage/{$context->getId()}";
    }


    /**
     * Transact from Unit Specification context
     *
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     */
    private function transactUnitSpecificationContext(): string
    {
        // The unit spec doesn't contain a reference back to its course spec.
        // It should, or I should be able to find one.
        $context = $this->unitSpecificationMapper->findById($this->getContextId());

        $this->assessmentMapperProxy->save($this->assessment);

        return $this->router->generate('course.modifyCourse', ['id' => $context->getCourse()->getId()]);
    }


    /**
     * Transact from Project context
     *
     * Check that you don't spawn a Project in a Project
     *
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DomainException
     */
    private function transactProjectContext(): string
    {
        if ($this->assessment instanceof Project) {
            throw new DomainException('You cannot create nested projects.');
        }

        // TODO figure out what to do about this
        $this->assessmentMapperProxy->save($this->assessment);

        return "/homepage/{$this->getContextId()}";
    }


     /* Getters/Setters
     */

    /**
     * @return int
     */
    protected function getContextId(): int
    {
        return $this->assessment->getContext()->getId();
    }
}
