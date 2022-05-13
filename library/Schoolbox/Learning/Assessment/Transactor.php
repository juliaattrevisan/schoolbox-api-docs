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
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Config\Loader;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
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
     * @var QuizMapper
     */
    protected $quizMapper;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Replicator
     */
    protected Replicator $replicator;

    /**
     * Transactor constructor.
     *
     * @param FolderMapper                          $folderMapper
     * @param UnitImplementationMapper              $unitImplementationMapper
     * @param UnitSpecificationMapper               $unitSpecificationMapper
     * @param AssessmentMapperProxy                 $assessmentMapperProxy
     * @param DueWork\Response\File\Plagscan\Mapper $plagscanMapper
     * @param SingleFile\Mapper                     $submissionFileMapper
     * @param QuizMapper                            $quizMapper
     * @param Router                                $router
     * @param Replicator                            $replicator
     */
    public function __construct(
        FolderMapper $folderMapper,
        UnitImplementationMapper $unitImplementationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        DueWork\Response\File\Plagscan\Mapper $plagscanMapper,
        SingleFile\Mapper $submissionFileMapper,
        QuizMapper $quizMapper,
        Router $router,
        Replicator $replicator
    ) {
        $this->folderMapper = $folderMapper;
        $this->unitImplementationMapper = $unitImplementationMapper;
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->plagscanMapper = $plagscanMapper;
        $this->submissionFileMapper = $submissionFileMapper;
        $this->quizMapper = $quizMapper;
        $this->router = $router;
        $this->replicator = $replicator;
    }


    /**
     * Figure out a returnUrl when creating/updating assessments
     *
     * @param IAssessable      $assessment
     * @param User             $currentUser
     * @param array            $before
     * @param IAssessable|null $originalAssessment
     * @return array
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function transact(
        IAssessable $assessment,
        User $currentUser,
        array $before = [],
        ?IAssessable $originalAssessment = null
    ): array {
        $this->assessment = $assessment;
        $relocated = false;
        // Verify that the context of the assessment is valid
        $assessmentContext = $this->assessment->getContext();

        /* If you followed a creational link from a particular place,
         * go back to that place
         */
        if (!$this->assessment->getId()) {
            try {
                if ($assessmentContext instanceof Folder) {
                    $returnUrl = $this->transactFolderContext($currentUser);
                } elseif ($assessmentContext instanceof UnitImplementation) {
                    $returnUrl = $this->transactUnitImplementationContext($currentUser);
                } elseif ($assessmentContext instanceof UnitSpecification) {
                    $returnUrl = $this->transactUnitSpecificationContext($currentUser);
                } elseif ($assessmentContext instanceof Project) {
                    $returnUrl = $this->transactProjectContext($currentUser);
                } else {
                    throw new InvalidArgumentException('Please select a valid context');
                }
            } catch (MapperException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }
        } else {
            /* Otherwise you came from the update screen, just go back there for now
             */
            $this->assessmentMapperProxy->save($this->assessment, $currentUser);

            /* Due Work has a special thing going on where it can upload to Plagscan/Turnitin
             *
             * If Similarity Check was previously disabled, but has now been enabled,
             * Then attempt to retrofit all the previous submissions
             */
            if (($this->assessment instanceof DueWork\Model) && !$before['assessment']['similarityCheckEnabled']) {

                /** @var DueWork\Model $assessment */
                if ($assessment->getSimilarityCheckEnabled()) {
                    /* If turnitin is enabled, retrofit any old submissions' documents
                     * Otherwise do the original behaviour (retrofit to plag)
                     */
                    if ((new Turnitin\Helper(Loader::getInstance()))->isConfigured()) {
                        (new Turnitin\Retrofit(Turnitin\File\Mapper::getInstance(), $this->submissionFileMapper))->retrofit($this->assessment->getId());
                    } else {
                        (new DueWork\Response\File\Plagscan\Retrofit($this->plagscanMapper, $this->submissionFileMapper))->retrofit($assessment->getId());
                    }
                }
            }

            $returnUrl = $this->router->generate('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $this->assessment->getId()]);
        }

        // If relocated, remove original assessment
        if ($originalAssessment && $this->assessment->getId() !== $originalAssessment->getId()) {
            if ($this->assessment instanceof Quiz) {
                $this->quizMapper->copyQuizConstituents($originalAssessment->getId(), $this->assessment->getId());
            }
            if ($this->assessment instanceof Project) {
                /** @var Project $originalAssessment */
                $projectAssessments = $originalAssessment->getAssessables();
                foreach ($projectAssessments as $projectAssessment) {
                    $this->replicator->replicateInNewContext($projectAssessment, $this->assessment, $currentUser);
                }
            }
            $this->assessmentMapperProxy->delete($originalAssessment);
            $relocated = true;
        }

        /* If Similarity Check is enabled, and turnitin is enabled,
         * send the attachments to turnitin to use as exclusion data for student submissions
         */
        if ($this->assessment instanceof DueWork\Model && $this->assessment->getSimilarityCheckEnabled()) {
            /* If turnitin is enabled, create a group attachment for each file
             */
            if ((new Turnitin\Helper(Loader::getInstance()))->isConfigured()) {
                /* Note that $this->assessment and $assessment files do not have a primary id
                 * when you first create a Due Work, which means you have to obtain them by
                 * alternative means.
                 *
                 * new \Alaress_Probe($this->assessment->getAttachedFiles());
                 *
                 * TODO There should be an easier way to do this, but idk,
                 *  something is going funny in the swamp
                 */
                $submissionBox = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $this->assessment->getId(), ['cache' => false]);

                $turnitinGroupMapper = Turnitin\Group\Mapper::getInstance();

                /* Create a turnitin_group queue record if one doesn't already exist
                 * Upon save, set its status to Pending; we need to find out what files
                 * needed to be micromanaged into turnitin
                 */
                try {
                    $turnitinGroup = $turnitinGroupMapper->findOneByColumn('submission_box_id', $submissionBox->getId());
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $turnitinGroup = $turnitinGroupMapper->createModel()
                        ->setSubmissionBoxId($submissionBox->getId());
                }

                /* Set status to Pending; it needs to be processed asynchronously elsewhere
                 * because we don't know what changed, and there are a lot of api shenanigans
                 * to do
                 */
                $turnitinGroup->setStatus(Turnitin\Group\Model::STATUS_TRANSMIT_PENDING);

                $turnitinGroupMapper->save($turnitinGroup);
            }
        }

        /* The conclusion of how $returnUrl was derived
         */
        return [
            'assessment'         => $this->assessment,
            Redirect::RETURN_URL => $returnUrl,
            'relocated'          => $relocated,
        ];
    }


    /**
     * Transact from Folder context
     *
     * @param  User $currentUser
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    private function transactFolderContext(User $currentUser): string
    {

        /* TODO: Pass contextId as a param into this method
         * It should be obtained from the routes
         * You only get contextId when you're Creating, not updating
         */
        $context = $this->folderMapper->findById($this->assessment->getContext()->getId());

        $this->assessmentMapperProxy->save($this->assessment, $currentUser);

        return "/homepage/{$context->getId()}";
    }


    /**
     * Transact from Unit Implementation context
     *
     * @param  User $currentUser
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function transactUnitImplementationContext(User $currentUser): string
    {
        $context = $this->unitImplementationMapper->findById($this->assessment->getContext()->getId());

        $this->assessmentMapperProxy->save($this->assessment, $currentUser);

        return "/homepage/{$context->getId()}";
    }


    /**
     * Transact from Unit Specification context
     *
     * @param  User $currentUser
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     */
    private function transactUnitSpecificationContext(User $currentUser): string
    {
        // The unit spec doesn't contain a reference back to its course spec.
        // It should, or I should be able to find one.
        $context = $this->unitSpecificationMapper->findById($this->assessment->getContext()->getId());

        $this->assessmentMapperProxy->save($this->assessment, $currentUser);

        return $this->router->generate('course.modifyCourse', ['id' => $context->getCourse()->getId()]);
    }


    /**
     * Transact from Project context
     *
     * Check that you don't spawn a Project in a Project
     *
     * @param  User $currentUser
     * @return string
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DomainException
     */
    private function transactProjectContext(User $currentUser): string
    {
        if ($this->assessment instanceof Project) {
            throw new DomainException('You cannot create nested projects.');
        }

        // TODO figure out what to do about this
        $this->assessmentMapperProxy->save($this->assessment, $currentUser);

        return "/homepage/{$this->assessment->getContext()->getId()}";
    }
}
