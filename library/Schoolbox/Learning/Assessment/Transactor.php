<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Config\Loader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\LessonPlan\Activities\Replicator as LessonPlanActivitiesReplicator;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\FilesystemProxy;
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
     * Transactor constructor.
     *
     * @param FolderMapper                   $folderMapper
     * @param UnitImplementationMapper       $unitImplementationMapper
     * @param UnitSpecificationMapper        $unitSpecificationMapper
     * @param AssessmentMapperProxy          $assessmentMapperProxy
     * @param Turnitin\Group\Mapper          $turnitinGroupMapper
     * @param SubmissionBoxMapper            $submissionBoxMapper
     * @param SingleFile\Mapper              $submissionFileMapper
     * @param Generator                      $routeGenerator
     * @param Replicator                     $replicator
     * @param LessonPlanActivitiesReplicator $lessonPlanActivitiesReplicator
     * @param Kami\Connector                 $kamiConnector
     * @param FilesystemProxy                $filesystemProxy
     */
    public function __construct(
        private FolderMapper $folderMapper,
        private UnitImplementationMapper $unitImplementationMapper,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private Turnitin\Group\Mapper $turnitinGroupMapper,
        private SubmissionBoxMapper $submissionBoxMapper,
        private SingleFile\Mapper $submissionFileMapper,
        private Generator $routeGenerator,
        private Replicator $replicator,
        private LessonPlanActivitiesReplicator $lessonPlanActivitiesReplicator,
        private Kami\Connector $kamiConnector,
        private FilesystemProxy $filesystemProxy,
    ) {
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

        // Attach Kami interactive docs, before save
        // TODO should attach of Kami interactive docs be moved to post-save?
        //      In order to do that, need to extract Kami interactive docs to
        //      their own mapper.
        if (
            $originalAssessment instanceof DueWork\Model
            && $this->assessment instanceof DueWork\Model
            && $this->assessment->getKamiInteractive()
            && $this->assessment->getKamiFile()
            && $originalAssessment->getKamiFile()?->getHash() !== $this->assessment->getKamiFile()->getHash()
        ) {
            $document = $this->kamiConnector->uploadDocumentAsTemplate(
                $this->assessment->getKamiFile(),
                $this->filesystemProxy,
            );
            $this->assessment->setKamiInteractiveDocumentId($document->getId());
        }

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

            $returnUrl = $this->routeGenerator->generate('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $this->assessment->getId()]);
        }

        // If relocated, remove original assessment
        if ($originalAssessment && $this->assessment->getId() !== $originalAssessment->getId()) {
            if ($this->assessment instanceof Quiz) {
                $this->replicator->replicateQuizConstituents($originalAssessment, $this->assessment, $currentUser);
            }
            if ($this->assessment instanceof Project) {
                /** @var Project $originalAssessment */
                $projectAssessments = $originalAssessment->getAssessables();
                foreach ($projectAssessments as $projectAssessment) {
                    $this->replicator->replicateInNewContext($projectAssessment, $this->assessment, $currentUser);
                }
            }

            // Replicate lesson plan links
            $this->lessonPlanActivitiesReplicator->replicate($originalAssessment, $this->assessment);

            $this->assessmentMapperProxy->delete($originalAssessment);
            $relocated = true;
        }

        /* If Similarity Check is enabled, and turnitin is enabled, and this is an implementation,
         * send the attachments to turnitin to use as exclusion data for student submissions
         *
         * (We only care about assessment implementations because turnitin has no utility for
         * assessment specifications, so skip those ones)
         */
        if ($this->assessment instanceof DueWork\Model && $this->assessment->getSimilarityCheckEnabled() && $this->assessment->isImplementation()) {
            /* If turnitin is enabled, create a group attachment for each file
             */
            if ((new Turnitin\Helper(Loader::getInstance()))->isConfigured()) {
                /* Due Work has a special thing going on where it can upload to Plagscan/Turnitin
                 *
                 * If Similarity Check was previously disabled, but has now been enabled,
                 * Then attempt to retrofit all the previous submissions
                 */
                if (isset($before['assessment']) && !$before['assessment']['similarityCheckEnabled']) {
                    /* If turnitin is enabled, retrofit any old submissions' documents
                     */
                    (new Turnitin\Retrofit(Turnitin\Group\Mapper::getInstance(), Turnitin\File\Mapper::getInstance(), $this->submissionFileMapper, SubmissionBoxMapper::getInstance()))->retrofit($this->assessment->getId());
                } else {
                    /* Note that $this->assessment and $assessment files do not have a primary id
                     * when you first create a Due Work, which means you have to obtain them by
                     * alternative means.
                     *
                     * new \Alaress_Probe($this->assessment->getAttachedFiles());
                     *
                     * TODO There should be an easier way to do this, but idk,
                     *  something is going funny in the swamp
                     */
                    $submissionBox = $this->submissionBoxMapper->findOneByColumn('assessment_id', $this->assessment->getId(), [ 'cache' => false ]);

                    /* Create a turnitin_group queue record if one doesn't already exist
                     * Upon save, set its status to Pending; we need to find out what files
                     * needed to be micromanaged into turnitin
                     */
                    try {
                        $turnitinGroup = $this->turnitinGroupMapper->findOneByColumn('submission_box_id', $submissionBox->getId());
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $turnitinGroup = $this->turnitinGroupMapper->createModel()
                            ->setSubmissionBoxId($submissionBox->getId());
                    }

                    /* Set status to Pending; it needs to be processed asynchronously elsewhere
                     * because we don't know what changed, and there are a lot of api shenanigans
                     * to do
                     */
                    $turnitinGroup->setStatus(Turnitin\Group\Model::STATUS_TRANSMIT_PENDING);

                    $this->turnitinGroupMapper->save($turnitinGroup);
                }
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

        return $this->routeGenerator->generate('course.modifyCourse', ['id' => $context->getCourse()->getId()]);
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
        // TODO figure out what to do about this
        $this->assessmentMapperProxy->save($this->assessment, $currentUser);

        return "/homepage/{$this->assessment->getContext()->getId()}";
    }
}
